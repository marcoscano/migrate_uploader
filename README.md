# Migrate Uploader

**DISCLAIMER**: _This module is still in proof-of-concept phase, and you are
strongly discouraged to use it in production without carefully considering the
risks involved. But please test it and provide feedback, or help with its
development!_

## Overview

 This module provides a method for migrations that depend on files as source
 to be created without that information, and let the end user upload the actual
 files with the contents that should be used during the migration import.

 The **Migrate Uploader** module is only the base for the upload mechanism.
 Migrations should use as source plugins any of the plugins provided by the
 submodules:
 * **Migrate Uploader CSV**
 * **Migrate Uploader Spreadsheet**
 
 (or any other compatible source plugin)
 

## Installation

Install as usual, see [how to install drupal modules](https://www.drupal.org/docs/8/extending-drupal-8/installing-contributed-modules-find-import-enable-configure-drupal-8)
for further information.


## Configuration and Usage

As an example, if you want to upload CSV files through the UI on a migration,
 the steps you will need to follow are:
 
1) Install the submodule `migrate_uploader_csv` and its dependencies.
2) Create a migration that has that plugin as source, leaving the `path`
 property empty, such as:
 ```yaml
 id: foo_pages1
 langcode: en
 status: true
 dependencies:
   enforced:
     module:
       - my_module
 migration_group: foo
 label: 'Foo Pages 1'
 source:
   plugin: csv_uploader
   path: ''
   header_row_count: 1
   keys:
     - page_id
   column_names:
     0:
       page_id: Page identifier
     1:
       title: Title
     2:
       body: Body
   delimiter: ';'
 destination:
   plugin: 'entity:node'
 process:
   uid: '1'
   type:
     plugin: default_value
     default_value: page
   title: title
   body: body
 migration_dependencies:
   required: {}
   optional: {}
 ```
 The important part of this example is:
 ```yaml
  source:
    plugin: csv_uploader
    path: ''
 ```
3) Navigate to `/admin/structure/migrate/upload`, choose the migration you are
 interested in, and upload the CSV file.
4) Upon upload, you will be redirected to the UI where you can perform execution
 operations on this migration:
 `/admin/structure/migrate/manage/{migration_group}/migrations/{migration}/execute`
 Note that you can always come back to this page to import again (using the file
 that is on the server), rollback the import, etc.
 
Steps **3** and **4** can be repeated over time, so you can upload a new file
with additional or updated content, and import the migration again on the
"execute" page.