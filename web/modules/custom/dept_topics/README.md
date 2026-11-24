
### Definitions

**Parent node** - The topic/subtopic node that contains the reference to the child node. The user cannot edit the
contents of the topic/subtopic field_topic_content entity reference field but can change the order.
The list is updated when a child node selects or
deselected a topic/subtopic from it's field_site_topics.

**Child node** - the node that is referenced in a topic/subtopic node.
Not all nodes are available as a child node to a topic/subtopic. Only the target bundles selected in the
field_topic_content entity reference field are available.

**Active children** - Denotes a topic with child nodes that are not in archived moderation state

### Child content

Child content types have a site topics field (field_site_topics) which references topic/subtopic entities.
This field is used to both tag and assign the child node to a topic/subtopic.

The site topics field uses the topics tree widget settings to define if the current bundle should have topic content
entries added or removed for the selected values for that field. The topic tree widget also limits the available
topic/subtopic choices to those associated to the current domain (department).

Child content for topics/Subtopics is restricted by the allowed types set within the field_topic_content settings.

### Revisions
When child content is added, removed or it's position in the list is moved with a topic or subtopic the child ID will
be added/removed or reordered in the field_topic_content entity reference list for the current and all revisions of
that topic/subtopic node.

If child content has been published and a draft contains a change to the title, the daft title will not be displayed
on the topic until that draft is published.

When a child is archived, all references to it within the field_topic_content field of topics/subtopics are removed.
The child retains the selected site topics and if published then it will be added back into the field_topic_content


A topic/subtopic cannot be archived or deleted if it contains active children.

A topic can still be archived or deleted if it has archived children and other content types reference it via
'site topics' field.
On the delete confirmation page for the topic, the user will be displayed a warning to say it is referenced by other
content with a link to the entity usage for that topic.


