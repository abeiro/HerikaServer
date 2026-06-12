# CHIM Relationship System

## The Three Layers of NPC Intelligence

| Layer | System | What It Tracks |
|-------|--------|----------------|
| **Faction** | Kagrenac's Plugin | What factions an NPC *belongs to* (FormID from Skyrim) |
| **Relationship** | CHIM (this system) | How the NPC *feels* about individuals, groups, concepts |
| **Knowledge** | Oghma | What the NPC *knows* about the world |

---

## Two Operating Modes

### Mode 1: Dedicated Relationship LLM (Recommended)

Set `RELLLM_CONNECTOR` in Global Settings UI or `conf/conf.php`:
```php
$GLOBALS['RELLLM_CONNECTOR'] = 5; // ID of your relationship model connector
```

**How it works:**
- Uses your connector's configured settings (temperature, penalties, safety, etc.)
- Watches conversation context and decides when relationships change
- Conversation model only sees tier labels (Fond, Wary) - token efficient
- No `#REL:` commands needed from conversation model

**What conversation model sees:**
```
[RELATIONSHIPS]
Player: Fond (Platonic)
Lydia: Attached (Professional)
```

### Mode 2: Command-Based (Fallback)

If `RELLLM_CONNECTOR` is not set:
- Conversation model handles scoring via `#REL:Player=+15#` commands
- More token overhead but doesn't require separate LLM

**What conversation model sees:**
```
[RELATIONSHIPS]
Player: +35 (Fond, Platonic)
Lydia: +55 (Attached, Professional)
```

---

## Files

| File | Purpose |
|------|---------|
| `relationship_llm.php` | Dedicated LLM connector class for relationship processing |
| `context.php` | Injects relationship context into prompts |
| `postrequest.php` | Post-request hook for dynamic evaluation |
| `analyze_relationships.php` | AJAX endpoint for "Build with AI" button |
| `relationship_editor.php` | UI component for NPC Master modal |
| `npc_save_handler.php` | Merges relationship data on save |
| `batch_analyze.php` | CLI batch processing endpoint |
| `batch_build.php` | UI batch processing API for "Build Relationships" button |
| `async_queue.php` | Async queue processing for background relationship builds |

---

## RelationshipLLM Features

### 1. Static/Event Analysis
Generates JSONB relationship scores from available source context.
- "Build with AI" in the NPC modal analyzes recent event history involving that NPC
- "Build Relationships" bulk button on NPC Master processes existing TEXT relationships
- Infers faction biases from occupation (Imperial soldier -> Stormcloak=-50)

### 2. Dynamic Evaluation (`evaluateContext`)
Watches conversation context and decides if relationships should change.
- Called after each conversation turn
- Conservative: only changes for MEANINGFUL moments
- No #REL: commands needed from conversation model

### 3. NPC-to-NPC Evaluation (`evaluateNpcToNpc`)
Bidirectional relationship updates when two NPCs interact.
- Updates both speaker and listener relationships
- Considers existing relationships and context

### 4. Transitive Inference (`inferTransitiveRelationships`)
If A loves B (+80) and B hates C (-70), A becomes wary of C (-30).
- Runs periodically or on demand
- Capped at moderate levels (won't create extreme relationships)

### 5. Batch Processing
Process multiple NPCs at once via CLI:
```bash
php ext/relationship_system/batch_analyze.php limit=100 force=0 infer=1
```

Or use the "Build Relationships" button in the NPC Master UI for existing playthroughs.

---

## UI Features

### NPC Master Page
- **Build Relationships Button**: Bulk process existing NPCs with TEXT relationships into structured format
- **Per-NPC Modal**: Edit individual relationships, affinity scores, and types

### Relationship Editor (in NPC Modal)
- Visual relationship cards with affinity sliders
- Relationship type selector (Platonic, Romantic, Professional, etc.)
- "Build with AI" button for single-NPC analysis
- Add/remove relationships

---

## Installation

### Step 1: Set the Connector
In Global Settings UI, set "Relationship LLM Connector" to your preferred small/fast model.

Or add to `conf/conf.php`:
```php
$GLOBALS['RELLLM_CONNECTOR'] = 5; // Get ID from LLM Connectors UI
```

### Step 2: Configure Connector Settings
The RelationshipLLM uses ALL settings from your connector profile:
- Temperature
- Presence/Frequency/Repetition penalties
- Top-p, Top-k, Min-p, Top-a
- Safety settings (block_none for Google models)

### Step 3: Run Migration (One Time for Existing Data)
```bash
cd /var/www/html/HerikaServer
php debug/migrate_relationships_to_jsonb.php
```

Or use the "Build Relationships" button on the NPC Master page.

---

## Logging

```bash
tail -f /var/log/apache2/error.log | grep '\[REL'
```

- `[REL]` = Legacy command parsing
- `[REL-LLM]` = RelationshipLLM operations
- `[REL-AI]` = "Build with AI" analysis

---

## Tier System

| Affinity | Tier |
|----------|------|
| 76-100 | Devoted |
| 51-75 | Attached |
| 26-50 | Fond |
| 1-25 | Warm |
| 0 | Neutral |
| -1 to -25 | Wary |
| -26 to -50 | Cold |
| -51 to -75 | Resentful |
| -76 to -100 | Hostile |

---

## API

```php
require_once __DIR__ . '/lib/relationship_manager.php';

// Get relationship
$rel = RelationshipManager::getPlayerRelationship('Lydia');
// Returns: ['aff' => 45, 'type' => 'romantic', 'tier' => 'Fond']

// Set relationship
RelationshipManager::setRelationship('Lydia', 'Player', 80, 'romantic');
```

---

## Changelog

### December 27, 2025
- Added "Build Relationships" button to NPC Master page for bulk processing
- RelationshipLLM now uses connector's configured temperature and settings (no hardcoded overrides)
- Added support for all connector parameters: penalties, sampling, safety settings
- Fixed batch_build.php to properly load ApiBadge class
- Improved modal UI with gray/orange theme matching CHIM style

### December 22, 2025
- Initial release
