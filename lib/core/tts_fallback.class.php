<?php

class TTSFallback
{
    private const TABLE = 'core_tts_fallback';

    private const RACES = [
        'NordRace' => [
            'label' => 'Nord',
            'aliases' => ['Nord'],
            'male' => 'malenord',
            'female' => 'femalenord',
        ],
        'ImperialRace' => [
            'label' => 'Imperial',
            'aliases' => ['Imperial'],
            'male' => 'maleeventoned',
            'female' => 'femaleeventoned',
        ],
        'BretonRace' => [
            'label' => 'Breton',
            'aliases' => ['Breton'],
            'male' => 'maleeventoned',
            'female' => 'femaleeventoned',
        ],
        'RedguardRace' => [
            'label' => 'Redguard',
            'aliases' => ['Redguard'],
            'male' => 'maleeventonedaccented',
            'female' => 'femaleeventoned',
        ],
        'HighElfRace' => [
            'label' => 'High Elf',
            'aliases' => ['High Elf', 'Altmer'],
            'male' => 'maleelfhaughty',
            'female' => 'femaleelfhaughty',
        ],
        'WoodElfRace' => [
            'label' => 'Wood Elf',
            'aliases' => ['Wood Elf', 'Bosmer'],
            'male' => 'maleyoungeager',
            'female' => 'femaleyoungeager',
        ],
        'DarkElfRace' => [
            'label' => 'Dark Elf',
            'aliases' => ['Dark Elf', 'Dunmer'],
            'male' => 'maledarkelf',
            'female' => 'femaledarkelf',
        ],
        'OrcRace' => [
            'label' => 'Orc',
            'aliases' => ['Orc', 'Orsimer'],
            'male' => 'maleorc',
            'female' => 'femaleorc',
        ],
        'ArgonianRace' => [
            'label' => 'Argonian',
            'aliases' => ['Argonian'],
            'male' => 'maleargonian',
            'female' => 'femaleargonian',
        ],
        'KhajiitRace' => [
            'label' => 'Khajiit',
            'aliases' => ['Khajiit'],
            'male' => 'malekhajiit',
            'female' => 'femalekhajiit',
        ],
        'ElderRace' => [
            'label' => 'Elder',
            'aliases' => ['Elder', 'Old People Race', 'OldPeopleRace'],
            'male' => 'maleoldkindly',
            'female' => 'femaleoldkindly',
        ],
        'NordRaceChild' => [
            'label' => 'Nord Child',
            'aliases' => ['Nord Child', 'NordChildRace'],
            'male' => 'malechild',
            'female' => 'femalechild',
        ],
        'ImperialRaceChild' => [
            'label' => 'Imperial Child',
            'aliases' => ['Imperial Child', 'ImperialChildRace'],
            'male' => 'malechild',
            'female' => 'femalechild',
        ],
        'BretonRaceChild' => [
            'label' => 'Breton Child',
            'aliases' => ['Breton Child', 'BretonChildRace'],
            'male' => 'malechild',
            'female' => 'femalechild',
        ],
        'RedguardRaceChild' => [
            'label' => 'Redguard Child',
            'aliases' => ['Redguard Child', 'RedguardChildRace'],
            'male' => 'malechild',
            'female' => 'femalechild',
        ],
    ];

    public function getDefinitions(): array
    {
        return self::RACES;
    }

    public function normalizeRace($race): string
    {
        $token = $this->normalizeToken($race);
        if ($token === '') {
            return '';
        }

        foreach (self::RACES as $raceKey => $definition) {
            $candidates = array_merge(
                [$raceKey, $definition['label'] ?? ''],
                $definition['aliases'] ?? []
            );
            foreach ($candidates as $candidate) {
                if ($this->normalizeToken($candidate) === $token) {
                    return $raceKey;
                }
            }
        }

        return '';
    }

    public function normalizeGender($gender): string
    {
        $value = strtolower($this->scalarString($gender));
        if ($value === 'f' || str_contains($value, 'female')) {
            return 'female';
        }
        if ($value === 'm' || str_contains($value, 'male')) {
            return 'male';
        }

        return '';
    }

    public function getMatrix(): array
    {
        $matrix = [];
        foreach (self::RACES as $race => $definition) {
            $matrix[$race] = [
                'male' => $this->scalarString($definition['male'] ?? ''),
                'female' => $this->scalarString($definition['female'] ?? ''),
            ];
        }

        if (!$this->tableExists(self::TABLE)) {
            return $matrix;
        }

        $rows = $GLOBALS['db']->fetchAll(
            'SELECT race, gender, voiceid FROM public.' . self::TABLE . ' ORDER BY race, gender'
        );
        foreach (is_array($rows) ? $rows : [] as $row) {
            $race = $this->normalizeRace($row['race'] ?? '');
            $gender = $this->normalizeGender($row['gender'] ?? '');
            if ($race !== '' && $gender !== '') {
                $matrix[$race][$gender] = $this->scalarString($row['voiceid'] ?? '');
            }
        }

        return $matrix;
    }

    public function getVoice($race, $gender): string
    {
        $race = $this->normalizeRace($race);
        $gender = $this->normalizeGender($gender);
        if ($race === '' || $gender === '') {
            return '';
        }

        $matrix = $this->getMatrix();
        return $this->scalarString($matrix[$race][$gender] ?? '');
    }

    public function saveMatrix(array $submitted): bool
    {
        if (!$this->tableExists(self::TABLE)) {
            return false;
        }

        foreach (self::RACES as $race => $_definition) {
            $raceValues = $submitted[$race] ?? [];
            if (!is_array($raceValues)) {
                $raceValues = [];
            }
            foreach (['male', 'female'] as $gender) {
                $voiceId = $this->scalarString($raceValues[$gender] ?? '');
                $raceValue = $GLOBALS['db']->escapeLiteral($race);
                $genderValue = $GLOBALS['db']->escapeLiteral($gender);
                $voiceValue = $GLOBALS['db']->escapeLiteral($voiceId);
                $query = "
                    INSERT INTO public." . self::TABLE . " (race, gender, voiceid, updated_at)
                    VALUES ({$raceValue}, {$genderValue}, {$voiceValue}, CURRENT_TIMESTAMP)
                    ON CONFLICT (race, gender) DO UPDATE
                    SET voiceid = EXCLUDED.voiceid, updated_at = CURRENT_TIMESTAMP
                ";
                if ($GLOBALS['db']->execQuery($query) === false) {
                    return false;
                }
            }
        }

        return true;
    }

    public function getSuggestedVoiceIds(): array
    {
        $voiceIds = [];
        foreach (self::RACES as $definition) {
            foreach (['male', 'female'] as $gender) {
                $voiceId = $this->scalarString($definition[$gender] ?? '');
                if ($voiceId !== '') {
                    $voiceIds[strtolower($voiceId)] = $voiceId;
                }
            }
        }

        if ($this->tableExists('core_npc_master')) {
            $rows = $GLOBALS['db']->fetchAll(
                "SELECT DISTINCT voiceid FROM public.core_npc_master
                 WHERE NULLIF(BTRIM(COALESCE(voiceid, '')), '') IS NOT NULL
                 ORDER BY voiceid"
            );
            foreach (is_array($rows) ? $rows : [] as $row) {
                $voiceId = $this->scalarString($row['voiceid'] ?? '');
                if ($voiceId !== '') {
                    $voiceIds[strtolower($voiceId)] = $voiceId;
                }
            }
        }

        natcasesort($voiceIds);
        return array_values($voiceIds);
    }

    private function tableExists(string $table): bool
    {
        if (!isset($GLOBALS['db']) || !$GLOBALS['db']) {
            return false;
        }

        $tableValue = $GLOBALS['db']->escapeLiteral($table);
        $row = $GLOBALS['db']->fetchOne(
            "SELECT 1 AS present
             FROM information_schema.tables
             WHERE table_schema = 'public' AND table_name = {$tableValue}
             LIMIT 1"
        );

        return is_array($row) && intval($row['present'] ?? 0) === 1;
    }

    private function normalizeToken($value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', $this->scalarString($value)));
    }

    private function scalarString($value): string
    {
        return is_scalar($value) ? trim(strval($value)) : '';
    }
}
