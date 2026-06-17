<?php 

try {
    require_once $GLOBALS["ENGINE_PATH"] . "/lib/core/narrator.class.php";

    $narrator = new Narrator();
    
    // Ensure narrator has a profile_id set (default to profile 1 if not set)
    $profileId = $narrator->getProfileId();
    if ($profileId === null) {
        $profileMgr = new CoreProfile();
        $defProfile = $profileMgr->getDefaultNarrator();
        if ($defProfile) {
            $narrator->set('profile_id', (string)$defProfile['id']);
        } else {
            // Fallback to profile 1
            $narrator->set('profile_id', '1');
        }
    }
    
    // Ensure voiceid is set
    if (!$narrator->get('voiceid')) {
        $narrator->set('voiceid', 'TheNarrator');
    }
} catch (Exception $e) {
    // Narrator initialization failed, will use defaults
    Logger::warn("Narrator initialization failed: " . $e->getMessage());
} 

?>