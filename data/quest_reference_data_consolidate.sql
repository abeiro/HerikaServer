-- Consolidate quest reference rows to one array row per key.
-- Keeps active state as bool_or(active) per key and stores all formids in formids_json.

WITH expanded AS (
    SELECT
        type_key AS key_name,
        active,
        CASE WHEN formid >= 0 THEN formid ELSE NULL END AS formid
    FROM public.quest_item_types
    UNION ALL
    SELECT
        q.type_key AS key_name,
        q.active,
        CASE WHEN j.value ~ '^-?\d+$' THEN j.value::bigint ELSE NULL END AS formid
    FROM public.quest_item_types q
    CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(q.formids_json, '[]'::jsonb)) AS j(value)
),
normalized AS (
    SELECT
        key_name,
        bool_or(active) AS active,
        jsonb_agg(DISTINCT formid ORDER BY formid)
            FILTER (WHERE formid IS NOT NULL AND formid >= 0) AS formids_json
    FROM expanded
    WHERE key_name IS NOT NULL AND key_name <> ''
    GROUP BY key_name
),
deleted AS (
    DELETE FROM public.quest_item_types
)
INSERT INTO public.quest_item_types (type_key, formid, formids_json, active, note)
SELECT
    key_name,
    -1,
    COALESCE(formids_json, '[]'::jsonb),
    COALESCE(active, true),
    'auto-consolidated to array format'
FROM normalized;

WITH expanded AS (
    SELECT
        template_key AS key_name,
        active,
        CASE WHEN formid >= 0 THEN formid ELSE NULL END AS formid
    FROM public.quest_npc_templates
    UNION ALL
    SELECT
        q.template_key AS key_name,
        q.active,
        CASE WHEN j.value ~ '^-?\d+$' THEN j.value::bigint ELSE NULL END AS formid
    FROM public.quest_npc_templates q
    CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(q.formids_json, '[]'::jsonb)) AS j(value)
),
normalized AS (
    SELECT
        key_name,
        bool_or(active) AS active,
        jsonb_agg(DISTINCT formid ORDER BY formid)
            FILTER (WHERE formid IS NOT NULL AND formid >= 0) AS formids_json
    FROM expanded
    WHERE key_name IS NOT NULL AND key_name <> ''
    GROUP BY key_name
),
deleted AS (
    DELETE FROM public.quest_npc_templates
)
INSERT INTO public.quest_npc_templates (template_key, formid, formids_json, active, note)
SELECT
    key_name,
    -1,
    COALESCE(formids_json, '[]'::jsonb),
    COALESCE(active, true),
    'auto-consolidated to array format'
FROM normalized;

WITH expanded AS (
    SELECT
        template_key AS key_name,
        active,
        CASE WHEN formid >= 0 THEN formid ELSE NULL END AS formid
    FROM public.quest_npc_own_templates
    UNION ALL
    SELECT
        q.template_key AS key_name,
        q.active,
        CASE WHEN j.value ~ '^-?\d+$' THEN j.value::bigint ELSE NULL END AS formid
    FROM public.quest_npc_own_templates q
    CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(q.formids_json, '[]'::jsonb)) AS j(value)
),
normalized AS (
    SELECT
        key_name,
        bool_or(active) AS active,
        jsonb_agg(DISTINCT formid ORDER BY formid)
            FILTER (WHERE formid IS NOT NULL AND formid >= 0) AS formids_json
    FROM expanded
    WHERE key_name IS NOT NULL AND key_name <> ''
    GROUP BY key_name
),
deleted AS (
    DELETE FROM public.quest_npc_own_templates
)
INSERT INTO public.quest_npc_own_templates (template_key, formid, formids_json, active, note)
SELECT
    key_name,
    -1,
    COALESCE(formids_json, '[]'::jsonb),
    COALESCE(active, true),
    'auto-consolidated to array format'
FROM normalized;

WITH expanded AS (
    SELECT
        class_key AS key_name,
        active,
        CASE WHEN formid >= 0 THEN formid ELSE NULL END AS formid
    FROM public.quest_outfits
    UNION ALL
    SELECT
        q.class_key AS key_name,
        q.active,
        CASE WHEN j.value ~ '^-?\d+$' THEN j.value::bigint ELSE NULL END AS formid
    FROM public.quest_outfits q
    CROSS JOIN LATERAL jsonb_array_elements_text(COALESCE(q.formids_json, '[]'::jsonb)) AS j(value)
),
normalized AS (
    SELECT
        key_name,
        bool_or(active) AS active,
        jsonb_agg(DISTINCT formid ORDER BY formid)
            FILTER (WHERE formid IS NOT NULL AND formid >= 0) AS formids_json
    FROM expanded
    WHERE key_name IS NOT NULL AND key_name <> ''
    GROUP BY key_name
),
deleted AS (
    DELETE FROM public.quest_outfits
)
INSERT INTO public.quest_outfits (class_key, formid, formids_json, active, note)
SELECT
    key_name,
    -1,
    COALESCE(formids_json, '[]'::jsonb),
    COALESCE(active, true),
    'auto-consolidated to array format'
FROM normalized;
