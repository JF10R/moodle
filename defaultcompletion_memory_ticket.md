# Ticket: Memory exhaustion on course default completion admin page

## Summary
*What*: Loading `/course/defaultcompletion.php` builds full module editing forms for every activity type. On large sites this exhausts memory.

*Where*: Site administration → Courses → Default settings → Default completion.

*Who is affected*: Site managers with permissions to manage activity completion on sites hosting thousands of courses/activities.

## Description
When the site-level default completion page is opened, Moodle instantiates the default completion edit form for every module type the user can manage. Each default edit form immediately constructs the full `mod_form` for that module, including standard course module elements and related data (availability, outcomes, grading, question bank references, etc.). On sites with thousands of courses and large supporting datasets this results in dozens of heavyweight forms being held in memory simultaneously, eventually exhausting PHP’s memory limit and throwing fatal errors such as:

```
Fatal error: Allowed memory size of 134217728 bytes exhausted (tried to allocate 20480 bytes) in /lib/dml/mysqli_native_moodle_database.php on line 1369
```

### Expected behaviour
The default completion configuration page should render without exhausting memory, regardless of the number of courses or activities on the site.

### Actual behaviour
The page constructs every module form in advance and runs out of memory on large installations.

## Steps to reproduce
1. Create or use a Moodle site with thousands of courses and activities (or artificially lower the PHP memory limit to reproduce faster).
2. Log in as a site administrator (or another user with the capabilities required to manage module completion defaults).
3. Navigate to **Site administration → Courses → Default settings → Default completion** (`/course/defaultcompletion.php`).
4. Observe the page load failing with a PHP fatal error due to memory exhaustion.

## Impact
* Prevents administrators from configuring default completion settings on large sites.
* Blocks automation or bulk updates dependent on those defaults.

## Possible solutions
* Lazy-load module completion forms only when the user selects a module to edit.
* Provide a lightweight API so module plugins can expose just their completion defaults without building the full module editing form.
* At minimum, instantiate only the module forms specified via the `modids` filter instead of preloading every module.

## Recommended fix
Prioritise the third option—only instantiating default completion forms for module types that were explicitly requested via the `modids` filter. This keeps the change local to the existing renderer/forms code, follows Moodle’s preference for incremental, low-risk patches, and still honours the current UI contract (forms appear after the user picks the module in the selector). By stopping the renderer from blindly creating a `core_completion_defaultedit_form` for every module, we avoid triggering `manager::get_module_form()` for unused plugins, immediately removing the multi-megabyte form tree that currently accumulates in memory. The approach requires no new web service endpoints, JavaScript, or plugin callbacks, so it can be implemented with standard PHP changes and unit/Behat coverage.

## Workarounds
Temporarily increase the PHP memory limit, although this does not scale as more courses/activities are added.

## Environment
* Moodle version: current master (also reproducible on 4.x)
* PHP memory limit: default 128 MB (or lower)

