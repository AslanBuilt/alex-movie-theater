# /status

Show current project state in under 200 words:
- Current branch and last commit hash
- Unpushed commits (git log origin/master..HEAD --oneline)
- Untracked/modified files (git status --short)
- Live site last deploy (check GitHub Actions)
- Any open blockers from the last session

Do not load skills. Do not read source files. Just git status + recent log. Fast.
