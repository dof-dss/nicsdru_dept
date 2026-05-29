
# dept_fs - Department File System

## Description
Provides utilities for managing duplicate media files and consolidating them within a Drupal 10/11 site. It integrates with the `media_duplicates` module to identify duplicates based on custom file checksums and offers a bulk action to merge references to a single media entity.

## Features
- **Duplicate Media Detection**: Identifies media entities with identical file contents using a custom checksum processor that leverages an external CLI tool for accurate hashing.
- **Media Consolidation**: Provides a bulk action to consolidate duplicate media items. Administrators can select which media entity to keep, and the module will automatically update all entity references, media embeds, and entity usage records to point to the retained item.
- **Custom Checksum Processor**: Replaces the default file processor from `media_duplicates` with a custom processor that utilizes the `dof-dss-filehash` CLI tool for reliable file hashing.
- **Media Duplicates View**: Ships with a pre-configured View (`admin/content/media/duplicates`) to list, filter, and group duplicate media items by department and media type.

## Dependencies
This module requires the following:
- Drupal Core (`^10 || ^11`)
- Views
- Media & Media Library
- [Entity Usage](https://www.drupal.org/project/entity_usage)
- [Media Duplicates](https://www.drupal.org/project/media_duplicates)

## Installation & Configuration
1. Enable the required dependencies.
2. Enable `dept_fs`.
3. The module automatically registers the custom checksum processor and removes the default file processor from `media_duplicates`.
4. Ensure the `dof-dss-filehash` CLI tool is installed and accessible in the server's `PATH` environment, as it is required to generate checksums for media files.

## Usage
### Finding Duplicates
Navigate to **Structure > Media > Duplicates** (`/admin/content/media/duplicates`). The view groups media by file checksum and only displays groups containing multiple items. You can filter by media type, department, or name.

### Consolidating Media
1. On the Media Duplicates view, select the checkboxes for the media items you wish to consolidate (they must be duplicates of the same type).
2. Select **Consolidate Media** from the Bulk actions dropdown and click **Apply**.
3. On the confirmation page, select which media item should be retained as the primary copy.
4. Click **Consolidate**. The module will:
  - Update all entity references pointing to the duplicates to point to the retained item.
  - Update any embedded `drupal-media` tags in body/text fields to use the retained item's UUID.
  - Update Entity Usage records.
  - Invalidate relevant caches.

## Technical Notes
- The custom checksum processor relies on an external executable (`dof-dss-filehash`). Ensure proper permissions are set.
- Consolidation updates both base and revision tables for field data to maintain revision history accuracy.
- The module uses Drupal's private temp store to temporarily hold selected media IDs during the consolidation workflow.
