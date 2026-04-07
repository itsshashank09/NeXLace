import { createClient } from '@supabase/supabase-js'

const supabase = createClient(
  'https://qoriuwbkyjothmitpguq.supabase.co',
  'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFvcml1d2JreWpvdGhtaXRwZ3VxIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzU1Mzk3NTYsImV4cCI6MjA5MTExNTc1Nn0.X7Y14Ta6JQ6mBokY7r8R7SgMCoX8f2V2t0UM8DDw8rY'
)

export default supabase
