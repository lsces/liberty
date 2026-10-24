# Liberty Package — Developer Reference

Programmer-facing notes that are not part of the object model in `MANUAL.md`.

## Local Imports (`admin/admin_local_import.php`)

The page globs `BIT_ROOT_PATH.'config/local/import/*.php'`, `require`s each file and expects it to **return a
descriptor array**:

```php
return [
    'title'       => 'Short name shown as the form legend',
    'description' => 'One paragraph: what it does, what it needs, how to read its output.',
    'params'      => [
        'source' => [ 'label' => 'Source address', 'default' => 'https://example.org', 'size' => 40 ],
        'limit'  => [ 'label' => 'Maximum',        'default' => '20',                   'size' => 6 ],
    ],
    'run'         => function( array $pParams, bool $pApply ): string|array {
        // $pParams: the submitted values by name (defaults for anything not posted)
        // $pApply:  false = dry run (must not write), true = apply
        return [ 'line one', 'line two' ];   // a string, or a list of lines
    },
];
```

- The page does the bootstrap (`kernel/includes/setup_inc.php`), `verifyPermission( 'p_admin' )` and the
  confirmation on Apply; the script runs inside the application, so it can use any class (`global $gBitDb`,
  `$gBitSystem`, package classes) and `require_once` package include files.
- Posted values arrive as `p[<file>][<param>]` and are passed to `run` as strings. Validate them: a source
  address should be checked against a plain `http(s)://host[:port]` pattern before it is fetched.
- **A dry run must not write.** Count first, say what would change, and (for an apply) report what did.
- **Batch large jobs** (a handful of records per press for anything that downloads, resizes or rebuilds
  thumbnails) and print a closing line when more remain.
- Anything thrown from `run` is caught and shown in the result.
- Every descriptor file is `require`d each time the admin page is viewed, so keep the top level of the file to
  the `return [ ... ]` (no work, no output); all the work belongs in `run`.
- `run` must not bootstrap the application, check permissions or send headers: the page has done all three.
- Descriptor files are discovered by the file name; the file name is the key in the form, so keep it stable.
- Filing records through the normal classes (`store()`, `storePreference()`, `addToGalleries()`) keeps history
  and caches correct; a direct SQL edit skips both.

### Local Imports and package upgrades

Upgrade scripts (`<package>/admin/upgrades/<version>.php`) register steps with the installer, which runs them
in version order once per database and records the version. Local imports have no version record. If a site
needs "has this run here?" it should keep that in its own script (a marker preference or xref row).
