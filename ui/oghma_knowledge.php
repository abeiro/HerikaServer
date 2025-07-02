<?php
// Database connection details
$host = 'localhost';
$port = '5432';
$dbname = 'dwemer';
$schema = 'public';
$username = 'dwemer';
$password = 'dwemer';

// Set content type to JSON
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Don't display errors in JSON response

try {
    // Connect to the database
    $conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");
    
    if (!$conn) {
        throw new Exception("Failed to connect to database: " . pg_last_error());
    }

    // Get POST parameters
    $npcName = $_POST['npc_name'] ?? '';
    $oghmaTagsString = $_POST['oghma_tags'] ?? '';
    $searchTerm = $_POST['search'] ?? '';
    $categoryFilter = $_POST['category'] ?? '';

    // Clean and prepare the knowledge array
    $oghmaKnowledgeArray = array_map('trim', explode(',', $oghmaTagsString));
    $oghmaKnowledgeArray = array_filter($oghmaKnowledgeArray); // Remove empty values
    $oghmaKnowledgeArray[] = $npcName; // Add NPC name itself as a knowledge tag

    // If no knowledge tags, return empty result
    if (empty($oghmaKnowledgeArray)) {
        echo json_encode(['knowledge' => []]);
        exit;
    }

    // First, get all unique categories for the filter
    $categoriesQuery = "
        SELECT DISTINCT category 
        FROM {$schema}.oghma 
        WHERE category IS NOT NULL AND category != '' 
        ORDER BY category ASC
    ";
    $categoriesResult = pg_query($conn, $categoriesQuery);
    $categories = [];
    if ($categoriesResult) {
        while ($categoryRow = pg_fetch_assoc($categoriesResult)) {
            $categories[] = $categoryRow['category'];
        }
    }

    // Build the main query with optional filters
    $conditions = [];
    $params = [];
    $paramIndex = 1;

    // Add search filter if provided
    if (!empty($searchTerm)) {
        $conditions[] = "(LOWER(topic) LIKE LOWER($" . $paramIndex . ") OR LOWER(topic_desc) LIKE LOWER($" . $paramIndex . ") OR LOWER(topic_desc_basic) LIKE LOWER($" . $paramIndex . "))";
        $params[] = '%' . $searchTerm . '%';
        $paramIndex++;
    }

    // Add category filter if provided
    if (!empty($categoryFilter)) {
        $conditions[] = "category = $" . $paramIndex;
        $params[] = $categoryFilter;
        $paramIndex++;
    }

    $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    // Query to get all Oghma articles
    $query = "
        SELECT 
            topic,
            topic_desc,
            knowledge_class,
            knowledge_class_basic,
            topic_desc_basic,
            tags,
            category
        FROM {$schema}.oghma
        {$whereClause}
        ORDER BY topic ASC
    ";

    // Execute query with parameters if any
    if (!empty($params)) {
        $result = pg_query_params($conn, $query, $params);
    } else {
        $result = pg_query($conn, $query);
    }
    
    if (!$result) {
        throw new Exception("Database query failed: " . pg_last_error($conn));
    }

    $accessibleKnowledge = [];

    while ($row = pg_fetch_assoc($result)) {
        $topic = $row['topic'] ?? '';
        $topicDesc = $row['topic_desc'] ?? '';
        $knowledgeClass = trim($row['knowledge_class'] ?? '');
        $knowledgeClassBasic = trim($row['knowledge_class_basic'] ?? '');
        $topicDescBasic = $row['topic_desc_basic'] ?? '';
        $tags = $row['tags'] ?? '';
        $category = $row['category'] ?? '';

        $hasAdvancedAccess = false;
        $hasBasicAccess = false;

        // Check for 'knowall' override - grants access to everything
        $hasKnowAll = false;
        foreach ($oghmaKnowledgeArray as $userClass) {
            if (strtolower(trim($userClass)) === 'knowall') {
                $hasKnowAll = true;
                break;
            }
        }

        if ($hasKnowAll) {
            $hasAdvancedAccess = true;
        } else {
            // Check advanced access
            if ($knowledgeClass === '') {
                // Empty knowledge class means no restriction
                $hasAdvancedAccess = true;
            } else {
                // Convert advanced classes to array
                $advClassesArr = array_map('trim', explode(',', $knowledgeClass));
                $advClassesArr = array_filter($advClassesArr);
                
                // Check if user has any of the required advanced classes
                $hasAdvancedKnowledge = array_intersect($advClassesArr, $oghmaKnowledgeArray);
                if (!empty($hasAdvancedKnowledge)) {
                    $hasAdvancedAccess = true;
                }
            }

            // If no advanced access, check basic access
            if (!$hasAdvancedAccess) {
                if ($knowledgeClassBasic === '') {
                    // Empty basic knowledge class means no restriction
                    $hasBasicAccess = true;
                } else {
                    // Convert basic classes to array
                    $basicClassesArr = array_map('trim', explode(',', $knowledgeClassBasic));
                    $basicClassesArr = array_filter($basicClassesArr);
                    
                    // Check if user has any of the required basic classes
                    $hasBasicKnowledge = array_intersect($basicClassesArr, $oghmaKnowledgeArray);
                    if (!empty($hasBasicKnowledge)) {
                        $hasBasicAccess = true;
                    }
                }
            }
        }

        // Add to accessible knowledge if has any access
        if ($hasAdvancedAccess || $hasBasicAccess) {
            $accessLevel = $hasAdvancedAccess ? 'Advanced' : 'Basic';
            $description = $hasAdvancedAccess ? $topicDesc : $topicDescBasic;
            
            // Clean up the description
            $description = strip_tags($description);
            $description = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
            
            $accessibleKnowledge[] = [
                'topic' => htmlspecialchars($topic, ENT_QUOTES, 'UTF-8'),
                'level' => $accessLevel,
                'description' => htmlspecialchars($description, ENT_QUOTES, 'UTF-8'),
                'tags' => htmlspecialchars($tags, ENT_QUOTES, 'UTF-8'),
                'category' => htmlspecialchars($category, ENT_QUOTES, 'UTF-8'),
                'knowledge_class' => $hasAdvancedAccess ? htmlspecialchars($knowledgeClass, ENT_QUOTES, 'UTF-8') : '',
                'knowledge_class_basic' => (!$hasAdvancedAccess && $hasBasicAccess) ? htmlspecialchars($knowledgeClassBasic, ENT_QUOTES, 'UTF-8') : ''
            ];
        }
    }

    // Sort by topic name
    usort($accessibleKnowledge, function($a, $b) {
        return strcmp($a['topic'], $b['topic']);
    });

    // Return the result
    echo json_encode([
        'knowledge' => $accessibleKnowledge,
        'categories' => $categories
    ]);

} catch (Exception $e) {
    error_log("Oghma Knowledge Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'knowledge' => []]);
} finally {
    if (isset($conn) && $conn) {
        pg_close($conn);
    }
}
?> 