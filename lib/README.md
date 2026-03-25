# lib/ folder

this folder contains the PHPWord library that is being use to generate the School Order (.docx) files.

I put it here instead of using Composer so you don't have to install anything extra.

## What's in here

- **phpoffice/phpword/** — the library that generates Word documents
- **phpoffice/math/** — a dependency that PHPWord needs
- **composer/** — the autoloader that loads everything automatically
- **autoload.php** — the main file that hooks it all together

## Important

Don't delete this folder. If you do, the School Order generation feature will break (the one in `registrar/generate_so.php`).

You don't need to touch anything in here. It's already wired up through `includes/helpers.php`.
