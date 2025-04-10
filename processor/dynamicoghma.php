<?php

function syncQuestWithOghma($questId, $stage) {
    global $db;
    
    // Find matching rows in oghma_dynamic
    $query = "SELECT * FROM oghma_dynamic WHERE id_quest = '{$questId}' AND stage = {$stage}";
    $dynamicRows = $db->fetchAll($query);
    
    if (!empty($dynamicRows)) {
        foreach ($dynamicRows as $dynamicRow) {
            // Only proceed if we have a topic
            if (!empty($dynamicRow['topic'])) {
                // Check if topic exists in oghma table
                $existsQuery = "SELECT topic FROM oghma WHERE topic = " . $db->quote($dynamicRow['topic']);
                $existsResult = $db->fetchAll($existsQuery);
                
                if (empty($existsResult)) {
                    // Topic doesn't exist - create new entry
                    $insertData = array(
                        'topic' => $dynamicRow['topic'],
                        'topic_desc' => $dynamicRow['topic_desc'],
                        'knowledge_class' => $dynamicRow['knowledge_class'],
                        'topic_desc_basic' => $dynamicRow['topic_desc_basic'],
                        'knowledge_class_basic' => $dynamicRow['knowledge_class_basic'],
                        'tags' => $dynamicRow['tags'],
                        'category' => $dynamicRow['category']
                    );
                    
                    // Insert new topic
                    $db->insert('oghma', $insertData);
                    
                    // Update native_vector for the new entry
                    $vectorQuery = "
                        UPDATE oghma 
                        SET native_vector = 
                            setweight(to_tsvector(coalesce(topic, '')), 'A')
                            || setweight(to_tsvector(coalesce(topic_desc, '')), 'B')
                            || setweight(to_tsvector(coalesce(knowledge_class, '')), 'B')
                            || setweight(to_tsvector(coalesce(topic_desc_basic, '')), 'C')
                            || setweight(to_tsvector(coalesce(knowledge_class_basic, '')), 'C')
                            || setweight(to_tsvector(coalesce(tags, '')), 'D')
                            || setweight(to_tsvector(coalesce(category, '')), 'D')
                        WHERE topic = " . $db->quote($dynamicRow['topic']);
                    $db->execQuery($vectorQuery);
                    
                } else {
                    // Topic exists - update with non-empty fields
                    $updateData = array();
                    
                    // Only include non-empty fields in the update
                    if (!empty($dynamicRow['topic_desc'])) {
                        $updateData['topic_desc'] = $dynamicRow['topic_desc'];
                    }
                    if (!empty($dynamicRow['knowledge_class'])) {
                        $updateData['knowledge_class'] = $dynamicRow['knowledge_class'];
                    }
                    if (!empty($dynamicRow['topic_desc_basic'])) {
                        $updateData['topic_desc_basic'] = $dynamicRow['topic_desc_basic'];
                    }
                    if (!empty($dynamicRow['knowledge_class_basic'])) {
                        $updateData['knowledge_class_basic'] = $dynamicRow['knowledge_class_basic'];
                    }
                    if (!empty($dynamicRow['tags'])) {
                        $updateData['tags'] = $dynamicRow['tags'];
                    }
                    if (!empty($dynamicRow['category'])) {
                        $updateData['category'] = $dynamicRow['category'];
                    }
                    
                    // Only perform update if we have data to update
                    if (!empty($updateData)) {
                        $db->updateRow(
                            'oghma',
                            $updateData,
                            "topic = " . $db->quote($dynamicRow['topic'])
                        );
                        
                        // Update native_vector after the update
                        $vectorQuery = "
                            UPDATE oghma 
                            SET native_vector = 
                                setweight(to_tsvector(coalesce(topic, '')), 'A')
                                || setweight(to_tsvector(coalesce(topic_desc, '')), 'B')
                                || setweight(to_tsvector(coalesce(knowledge_class, '')), 'B')
                                || setweight(to_tsvector(coalesce(topic_desc_basic, '')), 'C')
                                || setweight(to_tsvector(coalesce(knowledge_class_basic, '')), 'C')
                                || setweight(to_tsvector(coalesce(tags, '')), 'D')
                                || setweight(to_tsvector(coalesce(category, '')), 'D')
                            WHERE topic = " . $db->quote($dynamicRow['topic']);
                        $db->execQuery($vectorQuery);
                    }
                }
            }
        }
    }
} 