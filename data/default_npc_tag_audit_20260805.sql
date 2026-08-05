-- Verified default NPC tag corrections from the full 2026-08-05 audit.
-- merchant and child are controlled by exact UESP category evidence.
BEGIN;

CREATE OR REPLACE FUNCTION pg_temp.chim_set_csv_tag(current_tags text, wanted_tag text, should_have boolean)
RETURNS text
LANGUAGE sql
IMMUTABLE
AS $$
    SELECT COALESCE(string_agg(tag, ', ' ORDER BY tag), '')
      FROM (
            SELECT DISTINCT lower(btrim(value)) AS tag
              FROM unnest(string_to_array(COALESCE(current_tags, ''), ',')) AS value
             WHERE btrim(value) <> ''
               AND lower(btrim(value)) <> lower(wanted_tag)
            UNION ALL
            SELECT lower(wanted_tag)
             WHERE should_have
      ) AS normalized_tags;
$$;

CREATE TEMP TABLE chim_default_npc_tag_audit (
    npc_name text PRIMARY KEY,
    merchant boolean,
    child boolean
) ON COMMIT DROP;

INSERT INTO chim_default_npc_tag_audit (npc_name, merchant, child) VALUES
        ('adara', NULL, TRUE),
        ('addvar', TRUE, NULL),
        ('adrianne_avenicci', TRUE, NULL),
        ('aeri', TRUE, NULL),
        ('aeta', NULL, TRUE),
        ('agni', NULL, TRUE),
        ('ahkari', TRUE, NULL),
        ('alesan', NULL, TRUE),
        ('alvor', TRUE, NULL),
        ('ambarys_rendar', TRUE, NULL),
        ('ancarion', TRUE, NULL),
        ('angeline_morrard', TRUE, NULL),
        ('anoriath', TRUE, NULL),
        ('anton_virane', TRUE, NULL),
        ('arcadia', TRUE, NULL),
        ('arnskar_ember-master', TRUE, NULL),
        ('asbjorn_fire-tamer', TRUE, NULL),
        ('assur', NULL, TRUE),
        ('atahbah', TRUE, NULL),
        ('aval_atheron', TRUE, NULL),
        ('aventus_aretino', NULL, TRUE),
        ('babette', TRUE, TRUE),
        ('baldor_iron-shaper', TRUE, NULL),
        ('balimund', TRUE, NULL),
        ('beirand', TRUE, NULL),
        ('belethor', TRUE, NULL),
        ('bersi_honey-hand', TRUE, NULL),
        ('birna', TRUE, NULL),
        ('blaise', NULL, TRUE),
        ('bolar', TRUE, NULL),
        ('bolli', FALSE, NULL),
        ('bolund', TRUE, NULL),
        ('bothela', TRUE, NULL),
        ('bottar', NULL, TRUE),
        ('braith', NULL, TRUE),
        ('brand-shei', TRUE, NULL),
        ('brelas', FALSE, NULL),
        ('britte', NULL, TRUE),
        ('calcelmo', TRUE, NULL),
        ('camilla_valerius', TRUE, NULL),
        ('carlotta_valentia', TRUE, NULL),
        ('clinton_lylvieve', NULL, TRUE),
        ('colette_marence', TRUE, NULL),
        ('corpulus_vinius', TRUE, NULL),
        ('dagny', NULL, TRUE),
        ('dagur', TRUE, NULL),
        ('dealer', TRUE, NULL),
        ('dirge', TRUE, NULL),
        ('dorthe', NULL, TRUE),
        ('dremora_merchant', TRUE, NULL),
        ('drevis_neloren', TRUE, NULL),
        ('drifa', TRUE, NULL),
        ('dushnamub', TRUE, NULL),
        ('edla', TRUE, NULL),
        ('eirid', NULL, TRUE),
        ('elda_early-dawn', TRUE, NULL),
        ('elgrim', TRUE, NULL),
        ('elmus', TRUE, NULL),
        ('elrindir', TRUE, NULL),
        ('elynea_mothren', TRUE, NULL),
        ('endarie', TRUE, NULL),
        ('endon', TRUE, NULL),
        ('enthir', TRUE, NULL),
        ('eorlund_gray-mane', TRUE, NULL),
        ('erikur', FALSE, NULL),
        ('erith', NULL, TRUE),
        ('evette_san', TRUE, NULL),
        ('eydis', TRUE, NULL),
        ('faida', TRUE, NULL),
        ('falas_selvayn', TRUE, NULL),
        ('falion', TRUE, NULL),
        ('faralda', TRUE, NULL),
        ('farengar_secret-fire', TRUE, NULL),
        ('feran_sadri', TRUE, NULL),
        ('fethis_alor', TRUE, NULL),
        ('fihada', TRUE, NULL),
        ('filnjar', TRUE, NULL),
        ('fjotra', NULL, TRUE),
        ('frabbi', TRUE, NULL),
        ('fralia_gray-mane', TRUE, NULL),
        ('francois_beaufort', NULL, TRUE),
        ('frida', TRUE, NULL),
        ('frodnar', NULL, TRUE),
        ('frothar', NULL, TRUE),
        ('garyn_ienth', TRUE, NULL),
        ('geldis_sadri', TRUE, NULL),
        ('gharol', TRUE, NULL),
        ('ghorza_gra-bagol', TRUE, NULL),
        ('gilfre', TRUE, NULL),
        ('glover_mallory', TRUE, NULL),
        ('gralnach', NULL, TRUE),
        ('grelka', TRUE, NULL),
        ('greta', TRUE, NULL),
        ('grosta', TRUE, NULL),
        ('gulum-ei', TRUE, NULL),
        ('gunmar', TRUE, NULL),
        ('hadring', TRUE, NULL),
        ('hafjorg', TRUE, NULL),
        ('halbarn_iron-fur', TRUE, NULL),
        ('haming', NULL, TRUE),
        ('haran', TRUE, NULL),
        ('helgi+s_ghost', NULL, TRUE),
        ('herluin_lothaire', TRUE, NULL),
        ('hermir_strong-heart', TRUE, NULL),
        ('hert', TRUE, NULL),
        ('hestla', TRUE, NULL),
        ('hillevi_cruel-sea', TRUE, NULL),
        ('hod', TRUE, NULL),
        ('hogni_red-arm', TRUE, NULL),
        ('hrefna', NULL, TRUE),
        ('hroar', NULL, TRUE),
        ('hroki', NULL, TRUE),
        ('hulda', TRUE, NULL),
        ('hunter', TRUE, NULL),
        ('iddra', TRUE, NULL),
        ('imedhnain', TRUE, NULL),
        ('imperial_quartermaster', TRUE, NULL),
        ('indaryn', FALSE, NULL),
        ('ingrid', FALSE, NULL),
        ('jala', TRUE, NULL),
        ('jawanan', TRUE, NULL),
        ('jonna', TRUE, NULL),
        ('joric', NULL, TRUE),
        ('karita', FALSE, NULL),
        ('kayd', NULL, TRUE),
        ('keerava', TRUE, NULL),
        ('kerah', TRUE, NULL),
        ('kharag_gro-shurkul', TRUE, NULL),
        ('kleppr', TRUE, NULL),
        ('knud', NULL, TRUE),
        ('lami', TRUE, NULL),
        ('lars_battle-born', NULL, TRUE),
        ('leontius_salvius', TRUE, NULL),
        ('lisbet', TRUE, NULL),
        ('lod', TRUE, NULL),
        ('lucan_valerius', TRUE, NULL),
        ('lucia', NULL, TRUE),
        ('lynly_star-sung', TRUE, NULL),
        ('ma+dran', TRUE, NULL),
        ('ma+jhad', TRUE, NULL),
        ('madena', TRUE, NULL),
        ('madesi', TRUE, NULL),
        ('majni', TRUE, NULL),
        ('mallus_maccius', TRUE, NULL),
        ('malthyr_elenil', FALSE, NULL),
        ('marise_aravel', TRUE, NULL),
        ('mila_valentia', FALSE, TRUE),
        ('milore_ienth', TRUE, NULL),
        ('morven_stroud', FALSE, NULL),
        ('moth_gro-bagol', TRUE, NULL),
        ('mralki', TRUE, NULL),
        ('muiri', TRUE, NULL),
        ('murbul', TRUE, NULL),
        ('nelacar', TRUE, NULL),
        ('nelkir', NULL, TRUE),
        ('neloth', TRUE, NULL),
        ('nils', TRUE, NULL),
        ('niranye', TRUE, NULL),
        ('nurelion', TRUE, NULL),
        ('oengul_war-anvil', TRUE, NULL),
        ('orc_hunter', TRUE, NULL),
        ('orgnar', TRUE, NULL),
        ('peddler', TRUE, NULL),
        ('phinis_gestor', TRUE, NULL),
        ('quintus_navale', TRUE, NULL),
        ('razelan', FALSE, NULL),
        ('revus_sarvani', FALSE, NULL),
        ('revyn_sadri', TRUE, NULL),
        ('ri+saad', TRUE, NULL),
        ('rin', NULL, TRUE),
        ('romlyn_dreth', TRUE, NULL),
        ('ronthil', TRUE, NULL),
        ('runa_fair-shield', NULL, TRUE),
        ('rustleif', TRUE, NULL),
        ('saadia', TRUE, NULL),
        ('sabjorn', TRUE, NULL),
        ('samuel', NULL, TRUE),
        ('sayma', TRUE, NULL),
        ('seren', TRUE, NULL),
        ('sharamph', TRUE, NULL),
        ('shuftharz', TRUE, NULL),
        ('sissel', NULL, TRUE),
        ('skuli', TRUE, TRUE),
        ('smaref_ice-blade', NULL, TRUE),
        ('sofie', TRUE, TRUE),
        ('solaf', TRUE, NULL),
        ('sorine_jurard', TRUE, NULL),
        ('stormcloak_quartermaster', TRUE, NULL),
        ('svari', NULL, TRUE),
        ('sybille_stentor', TRUE, NULL),
        ('syndus', TRUE, NULL),
        ('taarie', TRUE, NULL),
        ('tacitus_sallustius', TRUE, NULL),
        ('talen-jei', TRUE, NULL),
        ('talvas_fathryon', TRUE, NULL),
        ('thonnir', TRUE, NULL),
        ('thoring', TRUE, NULL),
        ('tolfdir', TRUE, NULL),
        ('tonilia', TRUE, NULL),
        ('torbjorn_shatter-shield', FALSE, NULL),
        ('traveling_merchant', FALSE, NULL),
        ('ulfberth_war-bear', TRUE, NULL),
        ('ungrien', TRUE, NULL),
        ('urag_gro-shub', TRUE, NULL),
        ('valga_vinicia', TRUE, NULL),
        ('vanryth_gatharian', TRUE, NULL),
        ('vekel_the_man', TRUE, NULL),
        ('viriya', TRUE, NULL),
        ('vivienne_onis', TRUE, NULL),
        ('wilhelm', TRUE, NULL),
        ('wuunferth_the_unliving', TRUE, NULL),
        ('wylandriah', TRUE, NULL),
        ('ysolda', FALSE, NULL),
        ('zaria', TRUE, NULL),
        ('zaynabi', TRUE, NULL);

UPDATE public.npc_templates AS npc
   SET npc_misc = CASE
       WHEN audit.child IS NULL THEN
           CASE WHEN audit.merchant IS NULL THEN npc.npc_misc
                ELSE pg_temp.chim_set_csv_tag(npc.npc_misc, 'merchant', audit.merchant) END
       ELSE pg_temp.chim_set_csv_tag(
           CASE WHEN audit.merchant IS NULL THEN npc.npc_misc
                ELSE pg_temp.chim_set_csv_tag(npc.npc_misc, 'merchant', audit.merchant) END,
           'child',
           audit.child
       )
   END
  FROM chim_default_npc_tag_audit AS audit
 WHERE lower(npc.npc_name) = lower(audit.npc_name);

UPDATE public.bio_templates AS bio
   SET oghma_knowledge_tags = CASE
       WHEN audit.child IS NULL THEN
           CASE WHEN audit.merchant IS NULL THEN bio.oghma_knowledge_tags
                ELSE pg_temp.chim_set_csv_tag(bio.oghma_knowledge_tags, 'merchant', audit.merchant) END
       ELSE pg_temp.chim_set_csv_tag(
           CASE WHEN audit.merchant IS NULL THEN bio.oghma_knowledge_tags
                ELSE pg_temp.chim_set_csv_tag(bio.oghma_knowledge_tags, 'merchant', audit.merchant) END,
           'child',
           audit.child
       )
   END
  FROM chim_default_npc_tag_audit AS audit
 WHERE lower(bio.npc_name) = lower(audit.npc_name);

-- Correct the one confirmed legacy tag spelling in both default sources.
UPDATE public.npc_templates
   SET npc_misc = pg_temp.chim_set_csv_tag(
       pg_temp.chim_set_csv_tag(npc_misc, 'collegeofwinterold', FALSE),
       'collegeofwinterhold',
       TRUE
   )
 WHERE lower(npc_name) = 'neiva_deep_water';

UPDATE public.bio_templates
   SET oghma_knowledge_tags = pg_temp.chim_set_csv_tag(
       pg_temp.chim_set_csv_tag(oghma_knowledge_tags, 'collegeofwinterold', FALSE),
       'collegeofwinterhold',
       TRUE
   )
 WHERE lower(npc_name) = 'neiva_deep_water';

COMMIT;
