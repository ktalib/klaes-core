Both done.

Committed — 3c2db97 on main, 64 files, working tree clean. Covers the gender lookup work, the MLS serial allocation service, and the new deploy tooling.

The one command is scripts/deploy/make-deploy.ps1:


.\scripts\deploy\make-deploy.ps1                  # uncommitted changes (before committing)
.\scripts\deploy\make-deploy.ps1 -Since 3c2db97   # everything since that commit (after)
.\scripts\deploy\make-deploy.ps1 -Since 73f4281 -IncludeUncommitted
Both modes matter: once you commit, the working tree is clean and "not yet committed" finds nothing — so -Since <last commit live on production> is the mode you'll use from now on. For the next release, that's 3c2db97.

It mirrors changed files into C:\wamp64\www\_klaes_deploy, writes MANIFEST.txt and RELEASE-INFO.txt, copies in the backup helpers, and generates 2-RUN-MIGRATIONS.bat from whichever migrations are new in that release — so the --path pinning stays correct without hand-editing. It also handles deletions, which the hand-built folder couldn't: those can't travel by copying, so they go to DELETE-ON-PRODUCTION.txt for manual removal.

Two bugs found by testing it, both of which would have shipped broken releases:

git status --porcelain collapses a wholly-untracked directory into one dir/ entry — every file inside was silently missing. Fixed with -uall.
backup-production.ps1 had this release's file count hardcoded; it now reads the expected count from the manifest, so the "wrong folder" warning stays accurate release to release.
Verified: rebuilt this release from -Since 73f4281 and the manifest matches the hand-built one; -Since HEAD~1 matches git's own counts exactly (75 M / 17 A / 1 D) and produced the deletion list; bad refs are rejected.

The regenerated folder now has 46 modified / 15 new — one controller changed mid-session, and the automated build caught it where the hand-built folder had missed it. DEPLOY_README.md is preserved and updated; note it's hand-written, so it doesn't regenerate — the script would erase it if you rebuild without saving it first.


.\scripts\deploy\make-deploy.ps1                  # uncommitted changes (before committing)
.\scripts\deploy\make-deploy.ps1 -Since 3c2db97   # everything since that commit (after)
.\scripts\deploy\make-deploy.ps1 -Since 73f4281 -IncludeUncommitted
