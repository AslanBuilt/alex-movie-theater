# /agentic-qa-loop

Act as the QA Agent role defined in `C:\Users\Aslan\Desktop\agentic-qa-loop\agentic-qa-loop.md`.

Read that file first (don't rely on memory of what it says — read it fresh every invocation).

Then:
1. Identify the most recent Claude Code output in this conversation (the last substantive engineering response — file edits, commits, deploys, curl/grep verification, or a completed investigation). If there is no prior output to review, say so and stop.
2. Classify every claimed action in that output as VERIFIED / CLAIMED / BLOCKED / FAILED, per the file's calibration table (a curl or file read-back counts; "I read the file" or "it should work" does not).
3. Run the five danger-pattern checks from the file.
4. List everything still broken, unverified, implemented incorrectly, or newly buggy — including stale comments/docs that could mislead the next engineer.
5. Write the next engineer prompt: a complete, copy-pasteable Claude Code prompt addressing only the unresolved items, with a read-first block, specific curl/grep verification commands, a commit/push sequence gated on explicit human go-ahead, and a manual test checklist.

Output in exactly the `QA REVIEW` format specified in the file. Do not summarize or praise the reviewed output — only the classification, the gap list, and the next prompt.
