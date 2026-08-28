-- Both actor rows survive a merge. The optional owner is local to this playthrough.
DO $$
BEGIN
    ALTER TABLE public.core_npc_master ADD COLUMN IF NOT EXISTS profile_owner_npc_id integer;
    CREATE INDEX IF NOT EXISTS idx_npc_profile_owner ON public.core_npc_master (profile_owner_npc_id)
        WHERE profile_owner_npc_id IS NOT NULL;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid = 'public.core_npc_master'::regclass
                   AND conname = 'npc_profile_owner_not_self') THEN
        ALTER TABLE public.core_npc_master ADD CONSTRAINT npc_profile_owner_not_self
            CHECK (profile_owner_npc_id IS NULL OR profile_owner_npc_id <> id);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid = 'public.core_npc_master'::regclass
                   AND conname = 'npc_profile_owner_fk') THEN
        ALTER TABLE public.core_npc_master ADD CONSTRAINT npc_profile_owner_fk
            FOREIGN KEY (profile_owner_npc_id) REFERENCES public.core_npc_master(id)
            DEFERRABLE INITIALLY DEFERRED;
    END IF;
END;
$$;
