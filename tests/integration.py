"""Real PHP/MySQL workflow tests. Requires a disposable localhost MySQL server.

PHP_BINARY and MYSQL_BINARY identify installed executables. Database name must
start with nexlace_test_. No external API, SMTP or production service is used.
"""
import http.cookiejar
import json
import os
import secrets
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

repo = Path(__file__).resolve().parents[1]
php = os.environ.get('PHP_BINARY', 'php')
mysql_bin = os.environ.get('MYSQL_BINARY', 'mysql')
name = os.environ.get('NEXLACE_TEST_DB', 'nexlace_test_' + secrets.token_hex(5))
assert name.startswith('nexlace_test_') and name.replace('_', '').isalnum()
db_port = os.environ.get('NEXLACE_TEST_PORT', '33079')
http_port = int(os.environ.get('NEXLACE_TEST_HTTP_PORT', '18080'))
runtime = repo / '.test-runtime'
runtime.mkdir(exist_ok=True)
(runtime / 'sessions').mkdir(exist_ok=True)
checks = []

def sql(query):
    env = dict(os.environ, MYSQL_PWD=os.environ.get('NEXLACE_TEST_ADMIN_PASSWORD', ''))
    r = subprocess.run([mysql_bin, '--host=127.0.0.1', '--port='+db_port, '--user='+os.environ.get('NEXLACE_TEST_ADMIN_USER','root'), '--batch', '--skip-column-names'], input=query, text=True, encoding='utf-8', capture_output=True, env=env)
    if r.returncode: raise RuntimeError(r.stderr)
    return r.stdout.strip()

def check(value, message):
    assert value, message
    checks.append(message)
    print('PASS ' + message, flush=True)

class Client:
    def __init__(self):
        self.cookies = http.cookiejar.CookieJar()
        self.open = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))
        self.csrf = None

    def call(self, route, data=None, form=False, csrf=True):
        headers = {}
        body = None
        if data is not None:
            body = urllib.parse.urlencode(data).encode() if form else json.dumps(data).encode()
            headers['Content-Type'] = 'application/x-www-form-urlencoded' if form else 'application/json'
            if csrf and self.csrf: headers['X-CSRF-Token'] = self.csrf
        req = urllib.request.Request(f'http://127.0.0.1:{http_port}/'+route, data=body, headers=headers)
        try: response = self.open.open(req, timeout=10)
        except urllib.error.HTTPError as e: response = e
        text = response.read().decode()
        try: result = json.loads(text)
        except json.JSONDecodeError: result = {'html':text}
        return response.status, result

    def token(self):
        status,result = self.call('api/get_csrf_token.php')
        assert status == 200 and result['success']
        self.csrf = result['csrf_token']

    def register_login(self, label):
        self.token()
        email = label+'-'+secrets.token_hex(5)+'@example.test'
        password = secrets.token_urlsafe(24)
        data = {'name':label,'email':email,'password':password}
        status,result = self.call('api/register.php',data)
        check(status == 201 and result['success'], label+' registers against the fresh schema')
        status,result = self.call('api/login.php',{'email':email,'password':password})
        check(status == 200 and result['success'], label+' signs in with a verified password hash')
        self.id = int(result['data']['userId'])
        self.token()

server = None
app_user = 'test_app_' + secrets.token_hex(5)
app_password = secrets.token_hex(24)
try:
    # The guard above and fixed localhost destination apply to all setup/cleanup SQL.
    sql(f'CREATE DATABASE `{name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
    sql(f'USE `{name}`;\n' + (repo/'nexlace_schema.sql').read_text())
    sql(f"CREATE USER '{app_user}'@'localhost' IDENTIFIED BY '{app_password}'; GRANT SELECT,INSERT,UPDATE,DELETE ON `{name}`.* TO '{app_user}'@'localhost';")
    check(sql(f"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='{name}';")=='12','all twelve application tables install in an empty database')
    env = dict(os.environ, DB_HOST='127.0.0.1', DB_PORT=db_port, DB_NAME=name, DB_USER=app_user, DB_PASSWORD=app_password, GEMINI_API_KEY='')
    php_path = Path(php).resolve()
    flags = ['-d','display_errors=0','-d','session.save_path='+str(runtime/'sessions')]
    if (php_path.parent/'ext').is_dir(): flags += ['-d','extension_dir='+str(php_path.parent/'ext'),'-d','extension=pdo_mysql','-d','extension=fileinfo','-d','extension=mbstring']
    log = (runtime/'php.log').open('w')
    server = subprocess.Popen([php,*flags,'-S',f'127.0.0.1:{http_port}','-t',str(repo)],cwd=repo,env=env,stdout=log,stderr=log)
    guest = Client()
    for attempt in range(40):
        try: guest.token(); break
        except (OSError,urllib.error.URLError): time.sleep(0.1)
    else: raise RuntimeError('PHP test server did not start')
    check(guest.call('api/post_job.php',{'job_title':'blocked'},form=True)[0]==401,'anonymous job creation is denied')
    check(guest.call('api/register.php',{'name':'blocked'},csrf=False)[0]==403,'state-changing requests require session-bound CSRF')
    owner,developer,stranger = Client(),Client(),Client()
    for client,label in ((owner,'client'),(developer,'developer'),(stranger,'stranger')): client.register_login(label)
    profile = {'publish_profile':'1','headline':'PHP developer','rate':'25','availability':'more_than_30','location':'Test location','phone':'0000000000','bio':'Synthetic profile','skills':'PHP, MySQL','csrf_token':developer.csrf}
    check(developer.call('createdeveloperprofile.php',{k:v for k,v in profile.items() if k!='csrf_token'},form=True,csrf=False)[0]==403,'profile publishing rejects missing CSRF')
    status,result = developer.call('createdeveloperprofile.php',profile,form=True)
    check(status==200 and 'Developer profile created successfully' in result.get('html',''),'developer publishes a profile through the authenticated form')
    job = {'job_title':'Synthetic PHP job','job_details':'Test only','estimated_budget':'100','skills_required':'PHP'}
    check(owner.call('api/post_job.php',job,form=True)[1].get('success'),'owner posts a job without database DDL privileges')
    status,result = owner.call('findwork.php')
    check(status==200 and 'Synthetic PHP job' in result.get('html',''),'job listing reads the restored schema without DDL privileges')
    job_id = int(sql(f'SELECT id FROM `{name}`.post_jobs LIMIT 1;'))
    check(owner.call('api/apply_job.php',{'job_id':job_id})[0]==400,'a client cannot apply to their own job')
    status,result = developer.call('api/apply_job.php',{'job_id':job_id,'cover_letter':'Test application','proposed_rate':25})
    check(status==200 and result.get('success'),'developer applies and creates an invitation and notification')
    invite_id = int(sql(f'SELECT id FROM `{name}`.invitations LIMIT 1;'))
    check(stranger.call('api/respond_invitation.php',{'invitation_id':invite_id,'response':'accept'})[0]==404,'unrelated user cannot respond to another user’s invitation')
    check(owner.call('api/respond_invitation.php',{'invitation_id':invite_id,'response':'accept'})[1].get('success'),'recipient accepts the application')
    check(sql(f'SELECT status FROM `{name}`.job_applications LIMIT 1;')=='accepted','acceptance updates the linked application')
    check(developer.call('api/send_message.php',{'receiver_id':owner.id,'message':'Synthetic private message'})[1].get('success'),'authenticated users exchange a message')
    messages = owner.call('api/get_messages.php?user_id='+str(developer.id))[1]
    check(messages.get('success') and any(m['message']=='Synthetic private message' for m in messages['messages']),'recipient reads the persisted conversation')
    foreign = stranger.call('api/get_messages.php?user_id='+str(owner.id))[1]
    check(foreign.get('success') and not foreign['messages'],'third user cannot read another pair’s messages')
    notification_id = int(sql(f'SELECT id FROM `{name}`.notifications WHERE user_id={owner.id} LIMIT 1;'))
    check(not stranger.call('api/delete_notification.php',{'notification_id':notification_id})[1].get('success'),'notification deletion is scoped to its owner')
    check(developer.call('api/like_job.php',{'job_id':job_id})[1].get('success'),'authenticated job bookmarking persists')
    devices = developer.call('api/get_devices.php')[1]
    check(devices.get('success') and len(devices['devices'])==1,'device list is scoped to the signed-in user')
    device_id = int(devices['devices'][0]['id'])
    check(not stranger.call('api/revoke_session.php',{'device_id':device_id},form=True)[1].get('success'),'one user cannot revoke another user’s session')
    check(developer.call('api/revoke_session.php',{'device_id':device_id},form=True)[1].get('success'),'owner revokes their own session')
    check(developer.call('api/get_devices.php')[0]==401,'revoked session loses authenticated access')
    check(guest.call('setup_database.php')[0]==404,'database setup is inaccessible over HTTP')
    check(guest.call('scripts/provision-admin.php')[0]==404,'admin provisioning is inaccessible over HTTP')
    sql(f'UPDATE `{name}`.register SET is_active=0 WHERE Id={stranger.id};')
    check(stranger.call('api/get_devices.php')[0]==401,'account deactivation invalidates an existing session')
    admin = Client()
    admin.call('admin_panel/index.php',{'username':'admin','password':secrets.token_urlsafe(24)},form=True)
    check(sql(f'SELECT COUNT(*) FROM `{name}`.admin_users;')=='0','admin login does not create a default account')
    admin_password = secrets.token_urlsafe(24)
    provision = subprocess.run([php,*flags,'scripts/provision-admin.php','testadmin'],cwd=repo,env=dict(env,NEXLACE_ADMIN_PASSWORD=admin_password),capture_output=True,text=True)
    check(provision.returncode==0,'CLI provisions a hashed administrator with the DML-only account')
    status,result = admin.call('admin_panel/index.php',{'username':'testadmin','password':admin_password},form=True)
    check(status==200 and 'dashboard' in result.get('html','').lower() and sql(f'SELECT COUNT(*) FROM `{name}`.admin_users WHERE last_login IS NOT NULL;')=='1','administrator signs in using password_verify')
    print(json.dumps({'success':True,'checks':len(checks),'database':'disposable localhost MariaDB','php':'8.4','externalCalls':False}))
finally:
    if server is not None: server.terminate(); server.wait(timeout=10)
    sql(f'DROP DATABASE IF EXISTS `{name}`; DROP USER IF EXISTS \'{app_user}\'@\'localhost\';')
