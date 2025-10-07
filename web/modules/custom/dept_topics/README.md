
### Definitions

**Parent node** - The topic/subtopic node that contains the reference to the child node.

**Child node** - the node that is referenced in a topic/subtopic node.
Not all nodes are available as a child node to a topic/subtopic

Topic / Subtopic both have an entity reference field (field_topic_content)
for storing links to child content for that topic.

### Child content

Child content types have a site topics field (field_site_topics) which references topic/subtopic entities.
This field is used to both tag and assign the child node to a topic/subtopic.

The site topics field uses the topics tree widget settings to define if the current bundle should have topic content
entries added or removed for the selected values for that field. The topic tree widget also limits the available
topic/subtopic choices to those associated to the current domain (department).

Child content for topics/Subtopics is restricted by the allowed types set within the field_topic_content settings.

With a child node the selected values for topics/subtopics (field_site_topics) will be:
- added or removed when published/Quick published.
- removed when archived.

### Child

The mechanisms to update or remove child references are from 2 methods within the Topic Manager class
* updateChildOnTopics() - Adds or removes a child from topics.
* archiveChild() - Removes all references of the child from topics.

# WiP



