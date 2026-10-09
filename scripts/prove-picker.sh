#!/usr/bin/env bash
# Proves the record picker in Projects (the retrofit of ProcessCore's exemplar; app/picker.php, app/pickers.php, html/pick/index.php,
# shared/picker-rows.php, assets/js/record-picker.js; the design-system reference record-picker.md) on a SCRATCH database:
#   1. the code: php -l; the converted fields are pickers and no <select> of a member or a project remains in their forms;
#   2. under php -S (scripts/picker-router.php = the vhost's rewrites) as a Projects admin and a read-only member, both signed in through
#      the real /sso receiver with a hand-off token signed by the scratch key: the endpoint's refusals (anonymous, unknown source, a
#      gate that says no), the first page, the team first for a project, search by name / email / title, escaping, the 80-character cut,
#      paging through "Show more", a dependent param, a param that is not a number, visibility (a private project is not listed to
#      someone outside its team);
#   3. every converted form renders its picker under the old name and id with the chosen label; a 422 keeps the label; the saves go
#      through the hidden input (new issue, assign from the details column, project lead, add to the team, move to a project).
# Reads DB_NAME, APP_INTERNAL_PORT and ACTION_TOKEN_KEY from config/.env (the scratch install's own); repeats: its rows go first.
set -u
cd "$(dirname "$0")/.."
PORT=$(grep -E '^APP_INTERNAL_PORT=' config/.env | cut -d= -f2)
DB=$(grep -E '^DB_NAME=' config/.env | cut -d= -f2)
case "$DB" in *_dev|*_prove*|projects_w[0-9]) ;; *) echo "refusing to run against $DB: a proof writes — only a *_dev, *_prove* or projects_w<n> database (prove from a scratch checkout with its own config/.env)" >&2; exit 2;; esac
PGPW=${PGPASSWORD:-$(grep -E '^DB_PASSWORD=' config/.env | cut -d= -f2-)}
PGUSER_APP=$(grep -E '^DB_USER=' config/.env | cut -d= -f2-)
pass=0; fail=0
ok()  { pass=$((pass+1)); echo "ok   $1"; }
bad() { fail=$((fail+1)); echo "FAIL $1"; }
check(){ if eval "$2"; then ok "$1"; else bad "$1"; fi; }
sql() { PGPASSWORD=$PGPW psql -h 127.0.0.1 -U "$PGUSER_APP" -d "$DB" -At -c "$1"; }
LOG=/tmp/projects-picker-php.log
T=$(mktemp -d)

echo "== 1. the code"
for f in app/picker.php app/pickers.php html/pick/index.php app/views/shared/picker-rows.php app/views/layout.php app/features/projects/queries.php app/features/team/queries.php \
         app/features/activity/queries.php app/features/epics/queries.php app/views/issues/form.php app/views/issues/partials/fields.php app/views/issues/partials/move-project.php \
         app/views/projects/form.php app/views/projects/partials/settings-general.php app/views/projects/partials/settings-team.php app/views/projects/partials/labels-components.php \
         app/views/filters/page.php app/views/filters/form.php app/views/activity/page.php scripts/picker-router.php; do
  php -l "$f" >/dev/null 2>&1 && ok "lint $f" || bad "lint $f"; done
check "no <select> of a member or a project remains in the converted forms" "! grep -nE '<select[^>]*(name=\"(assignee|lead|default_assignee|member)\" id=\"(issue-field-assignee|project-form-field-lead|component-form-field-default-assignee|team-form-field-member)|name=\"project\" id=\"(filters-list-project|issue-move-project-field-project)\")|<select[^>]*name=\"member\" id=\"activity-filter-member\"|<select[^>]*id=\"filter-form-field-project\"' app/views -r"
check "the member select of an issue form, the lead selects and the select2 team member are gone" "! grep -rnE \"select\\('assignee'|select\\('lead'|data-select2-selector=\\\"team-member\\\"\" app/views"
check "the shell carries the one modal, its search box and the script" "grep -q 'id=\"record-picker\"' app/views/layout.php && grep -q 'hx-vals=\"js:{...recordPickerValues()}\"' app/views/layout.php && grep -q 'record-picker.js' app/views/layout.php"
check "app-overrides.css has the picker's section" "grep -q 'RECORD PICKER' html/assets/css/app-overrides.css"
check "node parses the script" "node --check html/assets/js/record-picker.js"

echo "== 2. the scratch install: members, projects, a signed-in admin and a read-only member"
sql "DELETE FROM projects WHERE key LIKE 'PK%';
     INSERT INTO members (id, member_kind, display_name, email, business_role, status, capability, job_title) VALUES
       (9001,'human','Pat Picker-Admin','pat@picker.test','super_admin','active','admin','Owner'),
       (9002,'human','Rita Picker-Reader','rita@picker.test','user','active','read','Reader')
       ON CONFLICT (id) DO UPDATE SET status='active', capability=EXCLUDED.capability, display_name=EXCLUDED.display_name, business_role=EXCLUDED.business_role;
     INSERT INTO members (id, member_kind, display_name, email, business_role, status, capability, job_title)
       SELECT 9100+n, CASE WHEN n % 10 = 0 THEN 'agent' ELSE 'human' END, format('Picker Person %s', lpad(n::text,2,'0')), format('pp%s@picker.test', n), 'user', 'active', 'write', format('Role %s', n)
         FROM generate_series(1,30) n
       ON CONFLICT (id) DO UPDATE SET status='active', display_name=EXCLUDED.display_name, job_title=EXCLUDED.job_title, member_kind=EXCLUDED.member_kind;
     INSERT INTO projects (key, name, lead_member_id, created_by) VALUES ('PKA','Picker Alpha',9001,9001);
     INSERT INTO projects (key, name, visibility, lead_member_id, created_by) VALUES ('PKP','Picker Private','private',9001,9001);
     INSERT INTO projects (key, name, created_by) SELECT format('PK%s', lpad(n::text,2,'0')), format('Picker Bulk %s', n), 9001 FROM generate_series(1,28) n;
     INSERT INTO project_team (project_id, member_id, project_role)
       SELECT p.id, m.id, m.role FROM projects p, (VALUES (9001,'lead'),(9101,'member'),(9102,'member')) AS m(id, role) WHERE p.key = 'PKA' ON CONFLICT (project_id, member_id) DO NOTHING;
     INSERT INTO project_team (project_id, member_id, project_role) SELECT id, 9001, 'lead' FROM projects WHERE key = 'PKP' ON CONFLICT (project_id, member_id) DO NOTHING" >/dev/null
PKA=$(sql "SELECT id FROM projects WHERE key='PKA'"); PKP=$(sql "SELECT id FROM projects WHERE key='PKP'")
MEMBERS=$(sql "SELECT count(*) FROM members WHERE status='active'")
php -S 127.0.0.1:$PORT -t html scripts/picker-router.php >$LOG 2>&1 & PHP_PID=$!
sleep 1.5
B="http://127.0.0.1:$PORT"
sso() { php -r '
  [$_, $mid, $name, $cap, $envf] = $argv;
  foreach (file($envf) as $l) { if (str_starts_with($l, "ACTION_TOKEN_KEY=")) { $key = trim(substr($l, 17)); } }
  $exp = time() + 60; $nonce = bin2hex(random_bytes(16)); $app = "projects";
  $tok = "$mid.$exp.$app.$nonce." . hash_hmac("sha256", "sso:$mid.$exp.$app.$nonce", $key);
  $c = rtrim(strtr(base64_encode(json_encode(["member_id" => (int) $mid, "display_name" => $name, "status" => "active", "capability" => $cap, "business_role" => $cap === "admin" ? "super_admin" : "user"])), "+/", "-_"), "=");
  echo "token=" . $tok . "&claims=" . rawurlencode($c . "." . hash_hmac("sha256", $c, $key));' "$@"; }
J=$T/admin.jar; JR=$T/reader.jar
code=$(curl -s -o /dev/null -w '%{http_code}' -c $J "$B/sso?$(sso 9001 'Pat Picker-Admin' admin config/.env)"); check "the admin signs in through /sso ($code)" "[ $code = 302 ]"
code=$(curl -s -o /dev/null -w '%{http_code}' -c $JR "$B/sso?$(sso 9002 'Rita Picker-Reader' read config/.env)"); check "the read-only member signs in through /sso ($code)" "[ $code = 302 ]"
get() { curl -s -b $J -c $J "$B$1"; }
getr() { curl -s -b $JR -c $JR "$B$1"; }
code() { curl -s -o $T/body.html -w '%{http_code}' -b ${2:-$J} "$B$1"; }
rows() { grep -c 'class="list-group-item list-group-item-action record-picker-row' ; }
labels() { grep -o 'data-label="[^"]*"' | sed 's/data-label="//;s/"$//'; }

echo "== 3. the endpoint"
check "anonymous: sent to the launcher, never the rows" "[ \$(curl -s -o /dev/null -w '%{http_code}' \"$B/pick/?source=member\") = 302 ]"
check "an unknown source is 404 with a fragment, not a page" "[ \$(code '/pick/?source=nope') = 404 ] && grep -q 'That list does not exist' $T/body.html && ! grep -q '<html' $T/body.html"
check "a source key is cleaned before the lookup" "[ \$(code '/pick/?source=../etc') = 404 ]"
check "a member may not add to a team without project.admin (403, a fragment)" "[ \$(code '/pick/?source=team_candidate&project_id=$PKA' $JR) = 403 ] && grep -q 'permission' $T/body.html && ! grep -q '<html' $T/body.html"
check "team_candidate without a project is refused" "[ \$(code '/pick/?source=team_candidate') = 403 ]"
check "a project the member may not see is refused as a source param (403)" "[ \$(code '/pick/?source=member&project_id=$PKP' $JR) = 403 ] && [ \$(code '/pick/?source=member&project_id=$PKP') = 200 ]"
check "a project the member may see is fine" "[ \$(code '/pick/?source=member&project_id=$PKA' $JR) = 200 ]"
get '/pick/?source=member' > $T/m1.html
FIRST=$(sql "SELECT display_name FROM members WHERE status='active' ORDER BY member_kind='agent', display_name, id LIMIT 1")
check "members, page 1: 25 rows, humans first in name order (first $FIRST of $MEMBERS)" "[ \$(rows < $T/m1.html) = 25 ] && [ \"\$(labels < $T/m1.html | head -1)\" = \"$FIRST\" ]"
check "an agent reads as the select did: the name and (agent)" "get '/pick/?source=member&q=Person%2010' | grep -q 'data-label=\"Picker Person 10 (agent)\"'"
check "the detail line is the job title" "grep -q 'record-picker-detail\">Role 3<' $T/m1.html"
check "Show more says 25 of $MEMBERS and asks for page 2" "grep -q \"25 of $MEMBERS shown\" $T/m1.html && grep -q 'page=2' $T/m1.html"
get '/pick/?source=member&page=2' > $T/m2.html
check "page 2 is the rest, rows only" "[ \$(grep -c 'id=\"record-picker-row-' $T/m2.html) = $((MEMBERS - 25)) ] && ! grep -q 'record-picker-list' $T/m2.html && ! grep -q 'record-picker-more' $T/m2.html"
get "/pick/?source=member&project_id=$PKA" > $T/mt.html
check "with a project the team comes first, badged Team" "[ \"\$(labels < $T/mt.html | head -3 | tr '\n' '|')\" = 'Pat Picker-Admin|Picker Person 01|Picker Person 02|' ] && [ \$(grep -c '>Team<' $T/mt.html) = 3 ]"
check "the project's id rides along to the next page" "grep -q 'project_id=$PKA' $T/mt.html"
check "search narrows by name, email and job title" "[ \$(get '/pick/?source=member&q=person%2007' | rows) = 1 ] && [ \$(get '/pick/?source=member&q=pp21@' | rows) = 1 ] && [ \$(get '/pick/?source=member&q=Role%2012' | rows) = 1 ]"
check "no match says so with the words escaped" "get '/pick/?source=member&q=%3Cb%3Enobody' | grep -q 'No member matches “&lt;b&gt;nobody”'"
check "a search longer than 80 characters is cut, not refused" "[ \$(code \"/pick/?source=member&q=\$(printf 'a%.0s' \$(seq 1 120))\") = 200 ]"
check "a param that is not a number is ignored" "[ \$(code '/pick/?source=member&project_id=abc') = 200 ] && ! grep -q '>Team<' $T/body.html"
check "an undeclared param changes nothing" "[ \$(get '/pick/?source=member&status=inactive&team=1' | rows) = 25 ]"
get "/pick/?source=team_candidate&project_id=$PKA" > $T/tc.html
check "team candidates leave out the team ($((MEMBERS - 3)) of them, 25 a page)" "[ \$(rows < $T/tc.html) = 25 ] && grep -q \"25 of $((MEMBERS - 3)) shown\" $T/tc.html && ! grep -q 'data-label=\"Picker Person 01\"' $T/tc.html && ! grep -q 'data-label=\"Pat Picker-Admin\"' $T/tc.html"
check "the chosen row is marked active and selected" "get '/pick/?source=member&selected=9101' | grep -q 'record-picker-row active\" id=\"record-picker-row-9101\" role=\"option\" aria-selected=\"true\"'"
get '/pick/?source=project' > $T/p1.html
check "projects, page 1: 25 rows by key; Show more says 25 of 30" "[ \$(rows < $T/p1.html) = 25 ] && [ \"\$(labels < $T/p1.html | head -1)\" = 'PK01 · Picker Bulk 1' ] && grep -q '25 of 30 shown' $T/p1.html"
check "projects: the admin sees the private one" "get '/pick/?source=project&q=PKP' | grep -q 'data-label=\"PKP · Picker Private\"'"
check "projects: the read-only member does not (visibility holds), and sees the 29 others" "! getr '/pick/?source=project&q=PKP' | grep -q 'data-label' && getr '/pick/?source=project' | grep -q '25 of 29 shown'"
check "move-to projects leave out the project itself ($PKA): 29 administered" "get '/pick/?source=project_admin&project_id=$PKA' | grep -q '25 of 29 shown' && ! get \"/pick/?source=project_admin&project_id=$PKA&q=Alpha\" | grep -q 'data-label'"
check "move-to projects: a member who administers none gets the empty state" "getr '/pick/?source=project_admin&project_id=$PKA' | grep -q 'Nothing to choose from yet'"
check "who has acted: the signed-in admin is in the trail" "get '/pick/?source=actor' | grep -q 'data-label=\"Pat Picker-Admin\"'"

echo "== 4. the forms"
csrf=$(get /projects | grep -o 'name="csrf-token" id="csrf-token-meta" content="[^"]*"' | sed 's/.*content="//;s/"//')
CSRF() { echo "csrf_token=$csrf"; }
get '/issues/new?project=PKA' > $T/issue-new.html
check "the page carries the modal once and the script once" "[ \$(grep -c 'id=\"record-picker\"' $T/issue-new.html) = 1 ] && [ \$(grep -c 'record-picker.js' $T/issue-new.html) = 1 ]"
check "new issue: the assignee is a picker under its old name and id, project-scoped, Unassigned while empty" "grep -q 'name=\"assignee\" id=\"issue-form-field-assignee\" value=\"\" data-picker-value' $T/issue-new.html && grep -q 'data-picker-source=\"member\"' $T/issue-new.html && grep -q 'data-picker-params=\"{&quot;project_id&quot;:$PKA}\"' $T/issue-new.html && grep -q 'record-picker-label text-muted\">Unassigned<' $T/issue-new.html"
check "new issue: the row label points at the opener" "grep -q 'for=\"issue-form-field-assignee-open\"' $T/issue-new.html"
get "/projects/PKA/edit" > $T/pform.html
check "project form: the lead is a picker showing the lead's name" "grep -q 'name=\"lead\" id=\"project-form-field-lead\" value=\"9001\" data-picker-value' $T/pform.html && grep -q 'record-picker-label\">Pat Picker-Admin<' $T/pform.html && grep -q 'for=\"project-form-field-lead-open\"' $T/pform.html"
get "/projects/new" > $T/pnew.html
check "new project: the lead picker is empty (None)" "grep -q 'name=\"lead\" id=\"project-form-field-lead\" value=\"\" data-picker-value' $T/pnew.html && grep -q 'record-picker-label text-muted\">None<' $T/pnew.html"
get "/projects/PKA/settings?tab=general" > $T/sg.html
check "settings, General: the lead is a picker" "grep -q 'name=\"lead\" id=\"project-form-field-lead\" value=\"9001\" data-picker-value' $T/sg.html"
get "/projects/PKA/settings?tab=team" > $T/st.html
check "settings, Team: Add to the team is a required picker over team_candidate (no Clear), without select2" "grep -q 'name=\"member\" id=\"team-form-field-member\" value=\"\" data-picker-value' $T/st.html && grep -q 'data-picker-source=\"team_candidate\"' $T/st.html && ! grep -q 'team-form-field-member-clear' $T/st.html && ! grep -q 'data-select2-selector=\"team-member\"' $T/st.html"
get "/projects/PKA/settings?tab=labels" > $T/sl.html
check "settings, Labels: the component's default assignee is a picker" "grep -q 'name=\"default_assignee\" id=\"component-form-field-default-assignee\" value=\"\" data-picker-value' $T/sl.html"
get '/filters/new' > $T/fnew.html
check "new filter: the project is a picker with Any project" "grep -q 'name=\"project\" id=\"filter-form-field-project\" value=\"\" data-picker-value' $T/fnew.html && grep -q 'record-picker-label text-muted\">Any project<' $T/fnew.html"
get '/filters' > $T/flist.html
check "saved filters: the project filter is a picker with Every project" "grep -q 'name=\"project\" id=\"filters-list-project\" value=\"\" data-picker-value' $T/flist.html && grep -q 'record-picker-label text-muted\">Every project<' $T/flist.html"
check "saved filters: a project in the URL shows its label" "get \"/filters?project=$PKA\" | grep -q 'record-picker-label\">PKA · Picker Alpha<'"
get '/activity?member=9001' > $T/act.html
check "activity: the Who filter is a picker showing the chosen person" "grep -q 'name=\"member\" id=\"activity-filter-member\" value=\"9001\" data-picker-value' $T/act.html && grep -q 'record-picker-label\">Pat Picker-Admin<' $T/act.html"
check "activity: the project filter stays a select (its value is the key)" "grep -q '<select name=\"project\" id=\"activity-filter-project\"' $T/act.html"
check "the board, issue-list and backlog assignee filters stay selects (Me / Unassigned are not records)" "grep -q 'name=\"assignee\" id=\"board-filter-assignee\"' app/views/projects/board.php && grep -q '<select name=\"assignee\" id=\"issues-list-filter-assignee\"' app/views/projects/issues.php && grep -q 'id=\"backlog-filter-assignee\"' app/views/projects/backlog.php"

echo "== 5. saving through the hidden input"
H=(-H 'HX-Request: true')
post() { curl -s -b $J -c $J "${H[@]}" -X POST --data-urlencode "csrf_token=$csrf" "$@"; }
postc() { curl -s -o $T/post.html -w '%{http_code}' -b $J -c $J "${H[@]}" -X POST --data-urlencode "csrf_token=$csrf" "$@"; }
c422=$(postc --data-urlencode project=PKA --data-urlencode type=task --data-urlencode summary= --data-urlencode priority=medium --data-urlencode assignee=9101 "$B/issues/save.php")
check "a 422 re-renders the issue form keeping the assignee's label ($c422)" "[ $c422 = 422 ] && grep -q 'name=\"assignee\" id=\"issue-form-field-assignee\" value=\"9101\" data-picker-value' $T/post.html && grep -q 'record-picker-label\">Picker Person 01<' $T/post.html"
c=$(postc --data-urlencode project=PKA --data-urlencode type=task --data-urlencode "summary=Picker proof issue" --data-urlencode priority=medium --data-urlencode assignee=9101 "$B/issues/save.php")
ISSUE=$(sql "SELECT id FROM issues WHERE summary='Picker proof issue' ORDER BY id DESC LIMIT 1")
check "an issue saves with the assignee posted by the hidden input ($c)" "[ -n \"$ISSUE\" ] && [ \"\$(sql \"SELECT assignee_member_id FROM issues WHERE id=$ISSUE\")\" = 9101 ]"
KEY=$(sql "SELECT key FROM issues WHERE id=$ISSUE")
get "/issues/$KEY" > $T/iv.html
check "the issue's details column: the assignee picker shows the label and posts to /issues/assign.php on change" "grep -q 'name=\"assignee\" id=\"issue-field-assignee\" value=\"9101\" data-picker-value hx-post=\"/issues/assign.php\" hx-trigger=\"change\"' $T/iv.html && grep -q 'record-picker-label\">Picker Person 01<' $T/iv.html && grep -q 'data-picker-params=\"{&quot;project_id&quot;:$PKA}\"' $T/iv.html"
c=$(postc --data-urlencode issue=$ISSUE --data-urlencode assignee=9102 "$B/issues/assign.php")
check "assign through the hidden input's post ($c)" "[ \"\$(sql \"SELECT assignee_member_id FROM issues WHERE id=$ISSUE\")\" = 9102 ]"
c=$(postc --data-urlencode issue=$ISSUE --data-urlencode assignee= "$B/issues/assign.php")
check "clearing the picker unassigns ($c)" "[ -z \"\$(sql \"SELECT assignee_member_id FROM issues WHERE id=$ISSUE\")\" ]"
check "the move-to-another-project panel carries a required picker over project_admin" "grep -q 'name=\"project\" id=\"issue-move-project-field-project\" value=\"\" data-picker-value' $T/iv.html && grep -q 'data-picker-source=\"project_admin\"' $T/iv.html && ! grep -q 'issue-move-project-field-project-clear' $T/iv.html"
c=$(postc --data-urlencode project=$PKA --data-urlencode "name=Picker Alpha" --data-urlencode lead=9102 --data-urlencode visibility=open --data-urlencode board_mode=scrum "$B/projects/save.php")
check "a project saves its lead through the hidden input ($c)" "[ \"\$(sql \"SELECT lead_member_id FROM projects WHERE id=$PKA\")\" = 9102 ]"
c422=$(postc --data-urlencode project=$PKA --data-urlencode "name=" --data-urlencode lead=9102 --data-urlencode visibility=open --data-urlencode board_mode=scrum "$B/projects/save.php")
check "a 422 on the project form keeps the lead's label ($c422)" "[ $c422 = 422 ] && grep -q 'record-picker-label\">Picker Person 02<' $T/post.html"
c=$(postc --data-urlencode project=$PKA --data-urlencode member=9103 --data-urlencode project_role=member "$B/team/save.php")
check "add to the team through the hidden input ($c)" "[ \"\$(sql \"SELECT count(*) FROM project_team WHERE project_id=$PKA AND member_id=9103 AND removed_at IS NULL\")\" = 1 ]"
check "the new team member is no longer a candidate, and now leads the project's member list" "! get \"/pick/?source=team_candidate&project_id=$PKA&q=person%2003\" | grep -q 'data-label' && get \"/pick/?source=member&project_id=$PKA\" | labels | head -5 | grep -q 'Picker Person 03'"
c=$(postc --data-urlencode issue=$ISSUE --data-urlencode project=$(sql "SELECT id FROM projects WHERE key='PK01'") "$B/issues/move-project.php")
check "move to another project through the hidden input ($c)" "[ \"\$(sql \"SELECT p.key FROM issues i JOIN projects p ON p.id=i.project_id WHERE i.id=$ISSUE\")\" = PK01 ]"
check "no PHP warning or notice in the server log" "! grep -qiE 'PHP (Warning|Notice|Fatal|Deprecated)' $LOG"
kill $PHP_PID 2>/dev/null; rm -rf $T
echo "== $pass passed, $fail failed"
[ $fail -eq 0 ]
