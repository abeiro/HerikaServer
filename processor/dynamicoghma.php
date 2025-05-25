<?php

function syncQuestWithOghma($questId, $stage) {
    Logger::debug("Processing Dynamic Oghma for Quest ID: $questId, Stage: $stage");
    
    // Database connection details
    $host = 'localhost';
    $port = '5432';
    $dbname = 'dwemer';
    $schema = 'public';
    $username = 'dwemer';
    $password = 'dwemer';

    // Connect to the database
    $conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");
    if (!$conn) {
        Logger::error("Failed to connect to database: " . pg_last_error());
        return;
    }

    // Find matching rows in oghma_dynamic
    $query = "SELECT * FROM $schema.oghma_dynamic WHERE id_quest = $1 AND stage = $2";
    $result = pg_query_params($conn, $query, [$questId, $stage]);
    
    if (!$result) {
        Logger::error("Error querying oghma_dynamic: " . pg_last_error($conn));
        return;
    }

    while ($dynamicRow = pg_fetch_assoc($result)) {
        // Only proceed if we have a topic
        if (!empty($dynamicRow['topic'])) {
            Logger::debug("Processing dynamic entry for topic: " . $dynamicRow['topic']);
            
            // Check if topic exists in oghma table
            $existsQuery = "SELECT topic FROM $schema.oghma WHERE topic = $1";
            $existsResult = pg_query_params($conn, $existsQuery, [$dynamicRow['topic']]);
            
            if (!$existsResult) {
                Logger::error("Error checking topic existence: " . pg_last_error($conn));
                continue;
            }

            if (pg_num_rows($existsResult) == 0) {
                Logger::debug("Creating new Oghma entry for topic: " . $dynamicRow['topic']);
                // Topic doesn't exist - create new entry
                $insertQuery = "
                    INSERT INTO $schema.oghma (
                        topic,
                        topic_desc,
                        knowledge_class,
                        topic_desc_basic,
                        knowledge_class_basic,
                        tags,
                        category
                    )
                    VALUES ($1, $2, $3, $4, $5, $6, $7)
                ";
                
                $insertResult = pg_query_params($conn, $insertQuery, [
                    $dynamicRow['topic'],
                    $dynamicRow['topic_desc'],
                    $dynamicRow['knowledge_class'],
                    $dynamicRow['topic_desc_basic'],
                    $dynamicRow['knowledge_class_basic'],
                    $dynamicRow['tags'],
                    $dynamicRow['category']
                ]);

                if (!$insertResult) {
                    Logger::error("Error inserting new topic: " . pg_last_error($conn));
                    continue;
                }
            } else {
                Logger::debug("Updating existing Oghma entry for topic: " . $dynamicRow['topic']);
                // Topic exists - update only non-empty fields or clear if 'clearall'
                $updateFields = [];
                $updateValues = [];
                $paramCount = 1;

                $fieldsToCheck = [
                    'topic_desc', 'knowledge_class', 'topic_desc_basic', 
                    'knowledge_class_basic', 'tags', 'category'
                ];

                foreach ($fieldsToCheck as $field) {
                    if (isset($dynamicRow[$field])) { // Check if the key exists
                        $value = $dynamicRow[$field];

                        if ($value === 'clearall') {
                            // Clear the field in oghma table
                            $updateFields[] = "$field = $" . $paramCount++;
                            $updateValues[] = null; // Use PHP null for SQL NULL
                        } elseif (!empty($value)) {
                            // Update with the non-empty value from oghma_dynamic
                            $updateFields[] = "$field = $" . $paramCount++;
                            $updateValues[] = $value;
                        }
                        // If $value is empty (but not 'clearall'), do nothing
                    }
                }

                if (!empty($updateFields)) {
                    $updateValues[] = $dynamicRow['topic']; // Add topic for WHERE clause
                    $updateQuery = "
                        UPDATE $schema.oghma 
                        SET " . implode(", ", $updateFields) . "
                        WHERE topic = $" . $paramCount;

                    $updateResult = pg_query_params($conn, $updateQuery, $updateValues);
                    if (!$updateResult) {
                        Logger::error("Error updating topic: " . pg_last_error($conn));
                        // Removed 'continue' as we are not inside the main loop here for this specific block
                    }
                } else {
                     Logger::debug("No fields to update or clear for existing topic: " . $dynamicRow['topic']);
                }
            }

            Logger::debug("Updating native_vector for topic: " . $dynamicRow['topic']);
            // Update native_vector
            $vectorQuery = "
                UPDATE $schema.oghma 
                SET native_vector = 
                    setweight(to_tsvector(coalesce(topic, '')), 'A')
                    || setweight(to_tsvector(coalesce(topic_desc, '')), 'B')
                    || setweight(to_tsvector(coalesce(topic_desc_basic, '')), 'C')
                WHERE topic = $1
            ";
            $vectorResult = pg_query_params($conn, $vectorQuery, [$dynamicRow['topic']]);
            if (!$vectorResult) {
                Logger::error("Error updating vector: " . pg_last_error($conn));
            }
        }
    }

    Logger::debug("Completed Dynamic Oghma processing for Quest ID: $questId, Stage: $stage");
    pg_close($conn);
} 