# NeXLace Project Log Book (96 Days)

This comprehensive log book details the engineering and development journey of **NeXLace**, a freelance marketplace platform connecting clients with developers. It covers the full lifecycle from initial concept to final deployment over a 96-day period.

---

## Phase 1: Planning, Research & Requirements (Days 1-14)
**Focus:** Project Definition, feasibility study, and foundation layout.

*   **Day 1: Project Ideation & Concept Validation**
    *   **Goal:** Define the core problem and solution.
    *   **Action:** Identified the need for a simplified, transparent freelance marketplace. defined the core loop: Client posts job -> Developer applies -> Work begins.
    *   **Outcome:** Drafted the "NeXLace" mission statement and core value proposition.

*   **Day 2: Market Research & Competitor Analysis**
    *   **Goal:** Understand existing solutions (Upwork, Freelancer, Fiverr).
    *   **Action:** Analyzed user flows on major platforms. Noted pain points: high fees, complex UIs, and slow vetting.
    *   **Outcome:** Decided on a "Zero-Friction" approach with a minimal, modern UI.

*   **Day 3: Scope Definition & Feature Locking**
    *   **Goal:** Finalize the MVP (Minimum Viable Product) feature set.
    *   **Action:** Locked key features: User Authentication, Profile Management, Job Posting System, Advanced Search/Filtering, and Real-time Messaging.
    *   **Outcome:** Created a high-level feature list document.

*   **Day 4: Technology Stack Selection**
    *   **Goal:** Choose the best tools for the job.
    *   **Decisions:**
        *   **Backend:** PHP 8+ (Reliable, widely supported).
        *   **Database:** MySQL (Relational data structure).
        *   **Frontend:** HTML5, Vanilla JavaScript (No heavy frameworks for speed), Tailwind CSS (Rapid styling).
        *   **Local Server:** Laragon (Apache).

*   **Day 5: Requirement Gathering & Specification**
    *   **Goal:** Detail functional and non-functional requirements.
    *   **Action:** Listed constraints (Performance: <2s load time, Security: XSS/SQLi protection) and user stories ("As a freelancer, I want to filter jobs by budget...").

*   **Day 6: Database Modeling (ERD)**
    *   **Goal:** Structure the data.
    *   **Action:** Designed the Entity-Relationship Diagram.
    *   **Schema:** `users` (id, email, pass, role), `jobs` (id, title, desc, budget, tags), `messages` (id, sender, receiver, content), `proposals` (id, job_id, user_id).

*   **Day 7: Low-Fidelity Wireframing (Paper)**
    *   **Goal:** Visualize the layout.
    *   **Action:** Sketched the "Happy Path" for a user signing up and posting a job. Focused on navigation hierarchy.

*   **Day 8: High-Fidelity Wireframing (Digital)**
    *   **Goal:** Create visual references.
    *   **Action:** Created mockups for the Dashboard, Search Results, and Chat Interface using design tools.
    *   **Outcome:** Finalized the "Card-based" layout for job listings.

*   **Day 9: Design System & Branding**
    *   **Goal:** Establish visual identity.
    *   **Action:** Selected the "Inter" font family for clean readability. Chose Primary Blue (`#135bec`) for actions to build trust and standard Grays for text.
    *   **Outcome:** Created a basic `style-guide.md`.

*   **Day 10: Environment Setup**
    *   **Goal:** Prepare local dev machine.
    *   **Action:** Installed Laragon. Configured virtual host `nexlace.test`. Verified PHP 8.2 and MySQL 8.0 installation.

*   **Day 11: Project Initialization**
    *   **Goal:** Create directory structure.
    *   **Action:** Set up folders: `api/` (endpoints), `config/` (DB), `assets/` (images/css), `includes/` (partials).
    *   **Outcome:** A clean, organized workspace.

*   **Day 12: Version Control (Git)**
    *   **Goal:** Secure code history.
    *   **Action:** `git init`. Created `.gitignore` to exclude `vendor/`, `.env`, and `uploads/`. Committed initial structure.

*   **Day 13: Frontend Architecture**
    *   **Goal:** efficient styling workflow.
    *   **Action:** Integrated Tailwind CSS via CDN for development speed (switched to CLI later for production). Created `index.html` boilerplate.

*   **Day 14: Phase 1 Review & Roadmap Adjustment**
    *   **Goal:** Sanity check.
    *   **Action:** Reviewed timeline against progress. Confirmed all tools are functional. Ready for code.

---

## Phase 2: Database & Authentication System (Days 15-30)
**Focus:** Building the secure backbone of the application.

*   **Day 15: Database Implementation**
    *   **Goal:** Create the physical database.
    *   **Action:** Used phpMyAdmin to create `nexlace` DB and `register` table with columns: `id`, `name`, `email`, `password`, `created_at`.

*   **Day 16: Secure Database Connection**
    *   **Goal:** PHP to MySQL communication.
    *   **Action:** Created `config/database.php`. Implemented PDO (PHP Data Objects) with error mode set to `EXCEPTION` for safe, catchable errors.

*   **Day 17: Registration UI**
    *   **Goal:** User signup form.
    *   **Action:** Built `register.html` with fields for Name, Email, Password. Added Client-side HTML5 validation (`required`, `type="email"`).

*   **Day 18: Registration Backend**
    *   **Goal:** Process signups.
    *   **Action:** Wrote `register_user.php`. Implemented `password_hash($pass, PASSWORD_DEFAULT)` for security. Added check for duplicate emails.

*   **Day 19: Login UI**
    *   **Goal:** User entrance.
    *   **Action:** Built `login.html`. Added concise error message containers.

*   **Day 20: Login Backend & Session Mgmt**
    *   **Goal:** Verify credentials.
    *   **Action:** Wrote `login_user.php`. Used `password_verify()`. On success, started `session_start()` and stored `user_id` and `user_name` in `$_SESSION`.

*   **Day 21: Logout Logic**
    *   **Goal:** Save exit.
    *   **Action:** Created `logout.php`. Used `session_unset()` and `session_destroy()`. Redirected to `index.html`.

*   **Day 22: Auth Guard Middleware**
    *   **Goal:** Protect private pages.
    *   **Action:** Added PHP session checks at the top of `mainpage.php` and `profile.php`. Redirects to login if session missing.

*   **Day 23: User Role Differentiation**
    *   **Goal:** Freelancers vs Clients.
    *   **Action:** Added `role` column to DB. Updated registration to allow selecting "I want to Hire" vs "I want to Work".

*   **Day 24: Password Reset Interface**
    *   **Goal:** Account recovery UI.
    *   **Action:** Built "Forgot Password" modal. (Note: Email sending logic scheduled for later phases).

*   **Day 25: Profile Database Schema**
    *   **Goal:** Store user details.
    *   **Action:** Altered `register` table. Added `professional_headline`, `bio`, `hourly_rate`, `skills`.

*   **Day 26: Profile Creation Flow**
    *   **Goal:** User onboarding.
    *   **Action:** Built `createdeveloperprofile.php`. structured multi-step form for easier data entry.

*   **Day 27: Profile Update Logic**
    *   **Goal:** Edit capability.
    *   **Action:** Wrote `update_profile.php`. Used `UPDATE` SQL statements with bound parameters (`:name`, `:bio`) to prevent injection.

*   **Day 28: File Upload System (Profile Pics)**
    *   **Goal:** User avatars.
    *   **Action:** Configured `enctype="multipart/form-data"`. Wrote logic to validate image types (jpg/png), generate unique names, and move to `uploads/` folder.

*   **Day 29: Universal Input Validation**
    *   **Goal:** Data integrity.
    *   **Action:** Created helper functions to sanitize input (`htmlspecialchars`, `trim`). Applied them to all existing POST handlers.

*   **Day 30: Authentication Stress Testing**
    *   **Goal:** QA.
    *   **Action:** Tested edge cases: empty fields, sql injection attempts in login, session timeout behavior. Fixed identified bugs.

---

## Phase 3: Core Features - Jobs & Search (Days 31-50)
**Focus:** The Engine of the Marketplace.

*   **Day 31: Job Schema Design**
    *   **Action:** Created `jobs` table. Key interactions: linked `client_id` to `register.id` (Foreign Key).

*   **Day 32: Job Posting Interface**
    *   **Action:** Built `postjob.html`. Layout includes Title, detailed WYSIWYG description, Budget selector, and Tags input.

*   **Day 33: Job Posting Backend**
    *   **Action:** Created `api/post_job.php`. Validates budget is numeric. Inserts job with `NOW()` timestamp.

*   **Day 34: Job Feed Foundation**
    *   **Action:** Created `findwork.php`. Set up the grid layout to display job cards.

*   **Day 35: Job Fetch API**
    *   **Action:** Wrote `api/get_jobs.php`. Returns JSON array of active jobs. Implemented error handling if DB is empty.

*   **Day 36: Dynamic DOM Rendering**
    *   **Action:** Wrote JS function `renderJobs(jobs)`. Uses Template Literals to generate HTML strings and inject them into the DOM efficiently.

*   **Day 37: Search Backend**
    *   **Action:** Implemented SQL `WHERE title LIKE :query` logic.

*   **Day 38: Search UI Integration**
    *   **Action:** Tied the Search Input `keyup` event to the `getJobs` API call with query parameters. Debounced the input to reduce API calls.

*   **Day 39: Filter System (Sidebar)**
    *   **Action:** Created sidebar UI with checkboxes for "Entry Level", "Intermediate", "Expert" and Radio buttons for "Hourly" vs "Fixed".

*   **Day 40: Advanced Multi-Filtering**
    *   **Action:** Updated backend builder to construct complex `WHERE` clauses dynamically based on received filter arrays.

*   **Day 41: "Saved Jobs" Functionality**
    *   **Action:** Created `saved_jobs` junction table. Added logic to toggle heart icon state and persist selection.

*   **Day 42: Job Detail View**
    *   **Action:** Created `job_details.php?id=xyz`. Fetches single job data. Displays full description and "Apply" button.

*   **Day 43: Proposal Architecture**
    *   **Action:** Designed `proposals` table.

*   **Day 44: Application UI**
    *   **Action:** Modal popup on "Apply" click. Fields for "Cover Letter" and "Bid Amount".

*   **Day 45: Proposal Submission Logic**
    *   **Action:** Backend script to save proposal. Checks if user has already applied to prevent duplicates.

*   **Day 46: Client Dashboard**
    *   **Action:** View for Clients to see "My Posted Jobs" and count of applicants.

*   **Day 47: Developer Dashboard**
    *   **Action:** View for Freelancers to see "My Active Proposals" and their status (Pending/Interview/Rejected).

*   **Day 48: Skill Tagging System**
    *   **Action:** Implemented logic to parse comma-separated tags and display them as colored pills on job cards.

*   **Day 49: Pagination Logic**
    *   **Action:** Added `LIMIT` and `OFFSET` to SQL queries to handle large datasets. Added "Next/Prev" buttons to UI.

*   **Day 50: Mid-Term Code Refactor**
    *   **Action:** Consolidated repetitive JS code into `utils.js`. Standardized API response formats (`{success: true, data: [...]}`).

---

## Phase 4: Messaging System (Days 51-70)
**Focus:** Enabling Real-time collaboration.

*   **Day 51: Message Schema**
    *   **Action:** `messages` table. Crucial decision: Use a single table for simplicity, indexing `sender_id` and `receiver_id` for speed.

*   **Day 52: Messaging Layout**
    *   **Action:** Built `message.php`. Left sidebar: Conversation list. Right pane: Active chat window.

*   **Day 53: Sending Mechanics**
    *   **Action:** `api/send_message.php`. Takes `receiver_id`, `message`. Returns the new message object for immediate UI append.

*   **Day 54: Retrieval Mechanics**
    *   **Action:** `api/get_messages.php`. params: `other_user_id`. Fetches chat history ordered by time ASC.

*   **Day 55: Conversation Grouping**
    *   **Action:** `api/get_conversations.php`. Complex SQL query to group messages by user pair and find the `MAX(timestamp)` to show the latest message preview.

*   **Day 56: Frontend Integration**
    *   **Action:** Wired up the "Send" button. Added "Enter key" listener for quick sending.

*   **Day 57: Real-time Polling**
    *   **Action:** Implemented `setInterval` (3s) to check for new messages. Added logic to only append *new* messages to avoid full re-renders.

*   **Day 58: Chat UI Polish**
    *   **Action:** CSS for message bubbles. Blue/Right for `me`, Gray/Left for `them`.

*   **Day 59: Attachment Support (DB)**
    *   **Action:** Added `attachment_path` and `attachment_name` columns to `messages`.

*   **Day 60: Attachment UI**
    *   **Action:** Hidden file input triggered by paperclip icon. Preview area for selected images before sending.

*   **Day 61: File Upload Handler**
    *   **Action:** Extended `send_message.php`. Checks for file existence, moves file to `uploads/messages/` with timestamped filename.

*   **Day 62: Rich Message Rendering**
    *   **Action:** JS logic: If message has attachment, render `<a>` tag or `<img>` tag inside the bubble.

*   **Day 63: Read Status (DB)**
    *   **Action:** Added `is_read` BOOLEAN default 0.

*   **Day 64: Read Status Logic**
    *   **Action:** When `get_messages.php` is called by the receiver, update relevant rows to `is_read = 1`.

*   **Day 65: Tick Indicators**
    *   **Action:** UI logic: One check = Sent. Two blue checks = Read (`is_read == 1`).

*   **Day 66: In-Chat Search**
    *   **Action:** Client-side JS filter to search specific text within the currently loaded conversation container.

*   **Day 67: User Search**
    *   **Action:** Backend search to find users to start a *new* conversation with.

*   **Day 68: Zero-Data States**
    *   **Action:** Created SVG illustration for "Select a conversation to start chatting".

*   **Day 69: Mobile Chat Optimization**
    *   **Action:** CSS Media queries to hide the sidebar when a chat is active on small screens, and add a "Back" button.

*   **Day 70: Stability Debugging**
    *   **Action:** Resolved issue where `sender_id` and `receiver_id` logic was creating duplicate conversation threads.

---

## Phase 5: Notifications & Advanced UI (Days 71-85)
**Focus:** Enhancing User Experience and Retention.

*   **Day 71: Notification System Design**
    *   **Action:** `notifications` DB table: `user_id`, `type`, `message`, `link`, `is_read`.

*   **Day 72: Event Triggers**
    *   **Action:** Added hooks: When Job Posted -> Notify matching freelancers (future). For now: When Message Received -> Notify user.

*   **Day 73: Notification Center**
    *   **Action:** `notification.php`. Lists alerts. Styled with "New" badge for unread items.

*   **Day 74: Header Badge**
    *   **Action:** AJAX call on page load to count unread notifications and display red number on the bell icon.

*   **Day 75: Batch Actions**
    *   **Action:** "Mark all as read" button. Updates all rows for user to `is_read = 1`.

*   **Day 76: Notification Cleanup**
    *   **Action:** Added Delete button (Trash icon) to individual notifications using `api/delete_notification.php`.

*   **Day 77: Design Refresh - Claymorphism**
    *   **Action:** Experimented with 3D button styles and soft shadows. (Iterative design phase).

*   **Day 78: Design Refresh - Glassmorphism**
    *   **Action:** Adopted Glassmorphism for final look. Frosted glass backgrounds (`backdrop-filter: blur`), white borders, and vibrant gradients.

*   **Day 79: Theme Engine (Dark Mode)**
    *   **Action:** Implemented Tailwind `dark:` classes. Added JS to toggle `<html>` class and persist preference in `localStorage`.

*   **Day 80: Help Center Static Content**
    *   **Action:** Drafted FAQ content for `help.php` ("How to hire?", "Payment security?").

*   **Day 81: Animated Accordions**
    *   **Action:** Built expandable FAQ items. Used CSS Grid transition trick for smooth height animation. Rotated chevron on open.

*   **Day 82: Review System Schema**
    *   **Action:** `reviews` table: `reviewer_id`, `reviewee_id`, `rating` (1-5), `comment`.

*   **Day 83: Review Modal**
    *   **Action:** Built a popup in `messages.php` allowing users to rate their interaction.

*   **Day 84: Review Submission**
    *   **Action:** Backend logic to save rating. Calculated average rating for user profile display.

*   **Day 85: Error Handling UI**
    *   **Action:** Created custom `404.html` and `500.html` pages to prevent showing raw server errors to users.

---

## Phase 6: Testing, Optimization & Deployment (Days 86-96)
**Focus:** Hardening the application for release.

*   **Day 86: Security Audit**
    *   **Action:** Reviewed all SQL queries. Verified `htmlspecialchars` on all outputs to prevent XSS. Checked file upload permissions.

*   **Day 87: Performance Optimization**
    *   **Action:** Added indexes to `jobs.title`, `users.email`. Compressed static assets.

*   **Day 88: Cross-Browser Compatibility**
    *   **Action:** Tested on Chrome, Firefox, Safari, Edge. Fixed flexbox alignment issues on Safari.

*   **Day 89: UI Final Polish**
    *   **Action:** Standardized padding/margins. Ensured consistent hover states on all interactive elements.

*   **Day 90: Codebase Cleanup**
    *   **Action:** Removed debug `console.log` statements. Deleted unused images and old backup files.

*   **Day 91: Documentation - Installation**
    *   **Action:** Wrote `README.md` details: Requirements (PHP 8, MySQL), Setup steps, DB import instructions.

*   **Day 92: Documentation - User Guide**
    *   **Action:** Created a simple "Getting Started" PDF/Page for end users.

*   **Day 93: Developer Log Finalization**
    *   **Action:** Completed this `ROADMAP_96_DAYS.md` file, documenting the journey.

*   **Day 94: Dry-Run Deployment**
    *   **Action:** Simulated a "Produciton" install on a fresh folder to verify all paths are relative and config is robust.

*   **Day 95: Showcase Preparation**
    *   **Action:** Recorded demo video. Took high-res screenshots of key flows (Signup, Job Post, Chat).

*   **Day 96: Final Commit**
    *   **Goal:** Project Handover.
    *   **Action:** `git commit -m "Final Release v1.0"`. Tagged release.

---
**Project Status:** COMPLETE
**Total Development Time:** 96 Days
