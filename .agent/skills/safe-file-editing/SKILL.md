---
name: Safe File Editing Practices
description: Guidelines for recovering from file editing errors and ensuring file integrity after a failed edit.
---

# Safe File Editing Practices

When using code editing tools (such as `replace_file_content` or `multi_replace_file_content`) to modify source code, errors occasionally occur due to mismatching `TargetContent`, incorrect spacing, or partial chunk application. 

Follow these rules when you encounter a file editing error:

1. **Verify File State Immediately**
   If an edit tool call returns an error (e.g., "target content not found" or "could not successfully apply any edits"), your FIRST action must be to use `view_file` to inspect the actual current content of the file around the lines you attempted to edit. Do not blindly attempt another edit without checking the file state, as partial edits may have occurred.

2. **Check for Partial Modifications**
   Even if an error is thrown, some chunks might have been successfully applied if multiple were provided, or a file might have been left in a broken syntax state. Check if the target was already modified or if duplicate code was accidentally inserted.

3. **Validate Syntax After Confusing Edits**
   If you are unsure whether the file is syntastically valid after a string of failed or confusing edits, run a syntax check (e.g., `npm run build`, `npx tsc --noEmit`, or `php -l`) using the `run_command` tool to catch any missing tags, brackets, or imports.

4. **Retry with Absolute Precision**
   When retrying a failed edit, ensure that your `TargetContent` perfectly matches the file's current state, including exact indentation, trailing whitespace, and newlines. If a large chunk is failing, either use `run_command` to output the exact target block, or break the edit down into smaller, highly specific chunks.
