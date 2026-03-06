# Revision Delete Tools

## INTRODUCTION

The **Revision Delete Tools** module provides efficient Drush commands for bulk deletion of entity revisions while optimizing performance through a queue system. It supports all entity types and ensures controlled revision cleanup without excessive memory usage.

The primary use cases for this module are:

- Removing excessive revisions from entities in a **memory-efficient way** using a queue-based approach.
- Handling large datasets where other revision deletion modules may run out of memory.
- Cleaning up entity revisions while keeping a specified number of the most recent revisions.

### Available Drush Commands
- **Queue revisions for all entities of the specified type:**
```drush rdt:remove-revisions node```

- **Queue revisions for all "page" nodes, keeping the last 5 revisions:**
```drush rdt:remove-revisions node page --keep=5```

- **Queue revisions for a specific node (ID 123) under "page":**
```drush rdtremove-revisions node page 123```

- **Queue revisions for a specific media entity (ID 123) under "image":**
```drush rdt:remove-revisions media image 123```

You can also use ``drush help rdt:remove-revisions`` for more instructions.

## MAINTAINERS

Revision Delete Tools was written and is maintained by Jordan Barnes.

- https://jordan-barnes.com
