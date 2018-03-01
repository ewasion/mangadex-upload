# mangadex-upload

## Configuration

### `$config`
- **default_regex**, **default_path**, **completed_path**, **default_group**, **default_lang**: Your preferred settings, can be changed through the web UI.
- **session_token**: Contained inside the "mangadex" cookie of a logged in user. (You might need to check "remember me" when logging in)

**default_regex** must match: (**1**) volume number, (**2**) chapter number, (**3**) group name  
**default_path** and **completed_path** must be absolute paths.

### `$group_db`
An associative array of group tag => group ID
### `$manga_db`
An associative array of manga name => manga ID


## Usage

Add the necessary groups/manga ID mappings (optional)  
Specify a directory containing zipped manga  
Fill in chapter titles (optional)  
Press "Start uploading"  
???  
Profit

## Examples

An example title list (`naruto-titles.txt`) is included.

## Alternatives

If you're looking for a client-side solution, [try this userscript](https://ewasion.github.io/userscripts/mangadex-upload/).
