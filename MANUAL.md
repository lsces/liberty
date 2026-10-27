# Liberty Package — Manual

What an administrator or site owner can do with Liberty. How it works underneath, and how to write code
against it, is in `DEVELOPER.md`.

## Local Imports (admin)

**Liberty admin > Local Imports** lists the one-off import scripts that belong to *this* site and runs
them. It is to data what **Local Scheme** is to the xref vocabulary: a gateway to files that live in the
site's own `config/local/` folder and are not part of any package.

- **Where the scripts live:** `config/local/import/*.php`, one import per file. Nothing is listed until a
  site puts a file there.
- **Who can use it:** administrators only (`p_admin`).
- **Running one:** each import shows its title, a description, its settings (source address, an id, a list of
  name matches, whatever the script asks for) and two buttons. **Dry run** reads and reports what would be
  done and writes nothing. **Apply** does it, after a confirmation. The result is printed on the page.
- **Batches:** an import that touches many records does a limited number per press and says so at the end;
  press Apply again until the report shows nothing left.
- **Errors:** an import that fails shows its message in the result instead of a blank page.

Imports are the site-specific counterpart of package upgrade scripts (`<package>/admin/upgrades/<version>.php`,
registered with `registerPackageUpgrade()`): upgrades are versioned and run once per database, an import is
chosen by an administrator, may be run again, and starts as a dry run. An import that anyone with the package
could use belongs in that package; one tied to a particular site's sources belongs in `config/local/import/`.
How to write one is in `DEVELOPER.md`.

## Local Scheme (admin)

**Liberty admin > Local Scheme** lists the xref scheme files that belong to *this* site and applies the
ones you tick. A scheme file adds or changes the tabs (groups) and fields (items) a content type can
carry, for vocabulary that only this site wants.

- **Where the files live:** `config/local/xref_schemes/*.php`. Nothing is listed until a site puts a file there.
- **Who can use it:** administrators only (`p_admin`).
- **Applying:** tick the files and press Apply. It is safe to press again: rows already in place are left
  alone, rows that differ are updated, missing ones are added, and the result shows how many of each.
- **Careful:** a file that declares a content type as *replaced* removes that type's whole existing set first.
  Apply every file that owns part of such a type in the same run.

How to write a scheme file is in `DEVELOPER.md`, under "Defining the vocabulary".

