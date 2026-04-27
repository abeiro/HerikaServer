<?php

define("PLAYER_REFID","0x00000014");
/**
 * Skyrim Papyrus Command Builder for AI Integration
 * Generates JSON payloads for AIAgentScriptProxy (Papyrus)
 * 
 * Usage:
 *   $skyrimCmd = new SkyrimCommandBuilder();
 *   $json = $skyrimCmd->Actor->ForceActorValue("0x00000014", "Health", 100.0);
 *   skyrimCmd->send($json); // via SSE-Net, HTTP, etc.
 */

class SkyrimCommandBuilder
{
    // === Actor Commands (cmdID 1-199) ===
    public $Actor;

    // === ObjectReference Commands (cmdID 100-199) ===
    public $ObjectReference;

    // === FormList Commands (cmdID 200-299) ===
    public $FormList;

    // === EffectShader Commands (cmdID 300-399) ===
    public $EffectShader;

    
    // === ActorUtil Commands (cmdID 400-499) ===
    public $ActorUtil;

    // === Faction Commands (cmdID 500-599) ===
    public $Faction;



    public function __construct()
    {
        $this->Actor = new class($this) {
            private $builder;

            public function __construct($builder) {
                $this->builder = $builder;
            }

            // 1
            public function SetActorValue(string $targetObjectFormId, string $asValueName, float $afValue): array {
                return $this->builder->build(1, compact('targetObjectFormId', 'asValueName', 'afValue'));
            }

            // 7
            public function Kill(string $targetObjectFormId): array {
                return $this->builder->build(7, compact('targetObjectFormId'));
            }

            // 8
            public function ModActorValue(string $targetObjectFormId, string $asValueName, float $afAmount): array {
                return $this->builder->build(8, compact('targetObjectFormId', 'asValueName', 'afAmount'));
            }

            // 9
            public function DamageActorValue(string $targetObjectFormId, string $asValueName, float $afDamage): array {
                return $this->builder->build(9, compact('targetObjectFormId', 'asValueName', 'afDamage'));
            }

            // 10
            public function RestoreActorValue(string $targetObjectFormId, string $asValueName, float $afAmount): array {
                return $this->builder->build(10, compact('targetObjectFormId', 'asValueName', 'afAmount'));
            }

            // 11
            public function ForceActorValue(string $targetObjectFormId, string $asValueName, float $afNewValue): array {
                return $this->builder->build(11, compact('targetObjectFormId', 'asValueName', 'afNewValue'));
            }

            // 12
            public function AddPerk(string $targetObjectFormId, string $akPerk): array {
                return $this->builder->build(12, compact('targetObjectFormId', 'akPerk'));
            }

            // 13
            public function RemovePerk(string $targetObjectFormId, string $akPerk): array {
                return $this->builder->build(13, compact('targetObjectFormId', 'akPerk'));
            }

            // 14
            public function AddSpell(string $targetObjectFormId, string $akSpell, int $abVerbose = 0): array {
                return $this->builder->build(14, compact('targetObjectFormId', 'akSpell', 'abVerbose'));
            }

            // 15
            public function RemoveSpell(string $targetObjectFormId, string $akSpell): array {
                return $this->builder->build(15, compact('targetObjectFormId', 'akSpell'));
            }

            // 16
            public function AddShout(string $targetObjectFormId, string $akShout): array {
                return $this->builder->build(16, compact('targetObjectFormId', 'akShout'));
            }

            // 17
            public function RemoveShout(string $targetObjectFormId, string $akShout): array {
                return $this->builder->build(17, compact('targetObjectFormId', 'akShout'));
            }

            // 18
            public function EquipShout(string $targetObjectFormId, string $akShout): array {
                return $this->builder->build(18, compact('targetObjectFormId', 'akShout'));
            }

            // 19
            public function EquipSpell(string $targetObjectFormId, string $akSpell, int $aiSource): array {
                return $this->builder->build(19, compact('targetObjectFormId', 'akSpell', 'aiSource'));
            }

            // 20
            public function UnequipShout(string $targetObjectFormId, string $akShout): array {
                return $this->builder->build(20, compact('targetObjectFormId', 'akShout'));
            }

            // 21
            public function UnequipSpell(string $targetObjectFormId, string $akSpell, int $aiSource): array {
                return $this->builder->build(21, compact('targetObjectFormId', 'akSpell', 'aiSource'));
            }

            // 5 / 22
            public function EquipItem(string $targetObjectFormId, string $akItem, bool $abPreventRemoval = false, bool $abSilent = true): array {
                return $this->builder->build(22, [
                    'targetObjectFormId' => $targetObjectFormId,
                    'akItem' => $akItem,
                    'abPreventRemoval' => (int)$abPreventRemoval,
                    'abSilent' => (int)$abSilent
                ]);
            }

            // 23
            public function UnequipItem(string $targetObjectFormId, string $akItem, bool $abPreventEquip = false, bool $abSilent = false): array {
                return $this->builder->build(23, [
                    'targetObjectFormId' => $targetObjectFormId,
                    'akItem' => $akItem,
                    'abPreventEquip' => (int)$abPreventEquip,
                    'abSilent' => (int)$abSilent
                ]);
            }

            // 24
            public function AddToFaction(string $targetObjectFormId, string $akFaction): array {
                return $this->builder->build(24, compact('targetObjectFormId', 'akFaction'));
            }

            // 25
            public function RemoveFromFaction(string $targetObjectFormId, string $akFaction): array {
                return $this->builder->build(25, compact('targetObjectFormId', 'akFaction'));
            }

            // 26
            public function SetFactionRank(string $targetObjectFormId, string $akFaction, int $aiRank): array {
                return $this->builder->build(26, compact('targetObjectFormId', 'akFaction', 'aiRank'));
            }

            // 27
            public function ModFactionRank(string $targetObjectFormId, string $akFaction, int $aiMod): array {
                return $this->builder->build(27, compact('targetObjectFormId', 'akFaction', 'aiMod'));
            }

            // 28
            public function EnableAI(string $targetObjectFormId, bool $abEnable = true): array {
                return $this->builder->build(28, ['targetObjectFormId' => $targetObjectFormId, 'abEnable' => (int)$abEnable]);
            }

            // 29
            public function AllowPCDialogue(string $targetObjectFormId, bool $abTalk = true): array {
                return $this->builder->build(29, ['targetObjectFormId' => $targetObjectFormId, 'abTalk' => (int)$abTalk]);
            }

            // 30
            public function SetAlert(string $targetObjectFormId, bool $abAlerted = true): array {
                return $this->builder->build(30, ['targetObjectFormId' => $targetObjectFormId, 'abAlerted' => (int)$abAlerted]);
            }

            // 31
            public function StartSneaking(string $targetObjectFormId): array {
                return $this->builder->build(31, compact('targetObjectFormId'));
            }

            // 32
            public function Dismount(string $targetObjectFormId): array {
                return $this->builder->build(32, compact('targetObjectFormId'));
            }

            // 33
            public function OpenInventory(string $targetObjectFormId, bool $abForceOpen = false): array {
                return $this->builder->build(33, ['targetObjectFormId' => $targetObjectFormId, 'abForceOpen' => (int)$abForceOpen]);
            }

            // 34
            public function PlayIdle(string $targetObjectFormId, string $akIdle): array {
                return $this->builder->build(34, compact('targetObjectFormId', 'akIdle'));
            }

            // 35
            public function PlayIdleWithTarget(string $targetObjectFormId, string $akIdle, string $akTarget): array {
                return $this->builder->build(35, compact('targetObjectFormId', 'akIdle', 'akTarget'));
            }

            // 36
            public function SetAlpha(string $targetObjectFormId, float $afTargetAlpha, bool $abFade = false): array {
                return $this->builder->build(36, [
                    'targetObjectFormId' => $targetObjectFormId,
                    'afTargetAlpha' => $afTargetAlpha,
                    'abFade' => (int)$abFade
                ]);
            }

            // 37
            public function SetGhost(string $targetObjectFormId, bool $abIsGhost = true): array {
                return $this->builder->build(37, ['targetObjectFormId' => $targetObjectFormId, 'abIsGhost' => (int)$abIsGhost]);
            }

            // 38
            public function SetUnconscious(string $targetObjectFormId, bool $abIsUnconscious = true): array {
                return $this->builder->build(38, ['targetObjectFormId' => $targetObjectFormId, 'abIsUnconscious' => (int)$abIsUnconscious]);
            }

            // 6
            public function StartCombat(string $targetObjectFormId, string $akTarget): array {
                return $this->builder->build(6, compact('targetObjectFormId', 'akTarget'));
            }

            // 45
            public function SendTrespassAlarm(string $targetObjectFormId, string $akCriminal): array {
                return $this->builder->build(45, compact('targetObjectFormId', 'akCriminal'));
            }

            // 46
            public function StartCannibal(string $targetObjectFormId, string $akTarget): array {
                return $this->builder->build(46, compact('targetObjectFormId', 'akTarget'));
            }

            // 47
            public function StartVampireFeed(string $targetObjectFormId, string $akTarget): array {
                return $this->builder->build(47, compact('targetObjectFormId', 'akTarget'));
            }

            // 48
            public function StopCombat(string $targetObjectFormId): array {
                return $this->builder->build(48, compact('targetObjectFormId'));
            }

            // 49
            public function DispelSpell(string $targetObjectFormId, string $akSpell): array {
                return $this->builder->build(49, compact('targetObjectFormId', 'akSpell'));
            }

            // 51
            public function SetExpressionOverride(string $targetObjectFormId, int $aiMood, int $aiStrength): array {
                return $this->builder->build(51, compact('targetObjectFormId', 'aiMood', 'aiStrength'));
            }

            // 52
            public function SetLookAt(string $targetObjectFormId, string $akTarget, bool $abPathingLookAt = false): array {
                return $this->builder->build(52, [
                    'targetObjectFormId' => $targetObjectFormId,
                    'akTarget' => $akTarget,
                    'abPathingLookAt' => (int)$abPathingLookAt
                ]);
            }

            // 54
            public function SetHeadTracking(string $targetObjectFormId, bool $abEnable = true): array {
                return $this->builder->build(54, ['targetObjectFormId' => $targetObjectFormId, 'abEnable' => (int)$abEnable]);
            }

            // 55
            public function SetDontMove(string $targetObjectFormId, bool $abDontMove = true): array {
                return $this->builder->build(55, ['targetObjectFormId' => $targetObjectFormId, 'abDontMove' => (int)$abDontMove]);
            }

            // 56
            public function KeepOffsetFromActor(
                string $targetObjectFormId,
                string $arTarget,
                float $afOffsetX,
                float $afOffsetY,
                float $afOffsetZ,
                float $afOffsetAngleX,
                float $afOffsetAngleY,
                float $afOffsetAngleZ,
                float $afCatchUpRadius,
                float $afFollowRadius
            ): array {
                return $this->builder->build(56, compact(
                    'targetObjectFormId', 'arTarget',
                    'afOffsetX', 'afOffsetY', 'afOffsetZ',
                    'afOffsetAngleX', 'afOffsetAngleY', 'afOffsetAngleZ',
                    'afCatchUpRadius', 'afFollowRadius'
                ));
            }

            // 57
            public function SetCriticalStage(string $targetObjectFormId, int $aiStage): array {
                return $this->builder->build(57, compact('targetObjectFormId', 'aiStage'));
            }

            // 58
            public function SetEyeTexture(string $targetObjectFormId, string $akTexture): array {
                return $this->builder->build(58, compact('targetObjectFormId', 'akTexture'));
            }

            // 59
            public function SetOutfit(string $targetObjectFormId, string $akOutfit, bool $abSleepOutfit = false): array {
                return $this->builder->build(59, [
                    'targetObjectFormId' => $targetObjectFormId,
                    'akOutfit' => $akOutfit,
                    'abSleepOutfit' => (int)$abSleepOutfit
                ]);
            }

            // 60
            public function SetRace(string $targetObjectFormId, string $akRace): array {
                return $this->builder->build(60, compact('targetObjectFormId', 'akRace'));
            }

            // 61
            public function SetCrimeFaction(string $targetObjectFormId, string $akFaction): array {
                return $this->builder->build(61, compact('targetObjectFormId', 'akFaction'));
            }

            // 62
            public function AllowBleedoutDialogue(string $targetObjectFormId, bool $abCanTalk = true): array {
                return $this->builder->build(62, ['targetObjectFormId' => $targetObjectFormId, 'abCanTalk' => (int)$abCanTalk]);
            }

            // 63
            public function SetNotShowOnStealthMeter(string $targetObjectFormId, bool $abNotShow = true): array {
                return $this->builder->build(63, ['targetObjectFormId' => $targetObjectFormId, 'abNotShow' => (int)$abNotShow]);
            }

            // 64
            public function SetRestrained(string $targetObjectFormId, bool $abRestrained = true): array {
                return $this->builder->build(64, ['targetObjectFormId' => $targetObjectFormId, 'abRestrained' => (int)$abRestrained]);
            }

            // 65
            public function SetNoBleedoutRecovery(string $targetObjectFormId, bool $abAllowed = false): array {
                return $this->builder->build(65, ['targetObjectFormId' => $targetObjectFormId, 'abAllowed' => (int)$abAllowed]);
            }

            // 66
            public function Resurrect(string $targetObjectFormId): array {
                return $this->builder->build(66, compact('targetObjectFormId'));
            }

            // 67
            public function ResetHealthAndLimbs(string $targetObjectFormId): array {
                return $this->builder->build(67, compact('targetObjectFormId'));
            }

            // 68
            public function SetPlayerControls(string $targetObjectFormId, bool $abControls = true): array {
                return $this->builder->build(68, ['targetObjectFormId' => $targetObjectFormId, 'abControls' => (int)$abControls]);
            }

            // 69
            public function SetAttackActorOnSight(string $targetObjectFormId, bool $abAttackOnSight = false): array {
                return $this->builder->build(69, ['targetObjectFormId' => $targetObjectFormId, 'abAttackOnSight' => (int)$abAttackOnSight]);
            }

            // 70
            public function SetForcedLandingMarker(string $targetObjectFormId, string $aMarker): array {
                return $this->builder->build(70, compact('targetObjectFormId', 'aMarker'));
            }

            // 71
            public function ClearForcedLandingMarker(string $targetObjectFormId): array {
                return $this->builder->build(71, compact('targetObjectFormId'));
            }

            // 73
            public function PathToReference(string $targetObjectFormId, string $aTarget, float $afWalkRunPercent): array {
                return $this->builder->build(73, compact('targetObjectFormId', 'aTarget', 'afWalkRunPercent'));
            }

            // 74
            public function DrawWeapon(string $targetObjectFormId): array {
                return $this->builder->build(74, compact('targetObjectFormId'));
            }

            // 75
            public function SheatheWeapon(string $targetObjectFormId): array {
                return $this->builder->build(75, compact('targetObjectFormId'));
            }

            // 76
            public function UnequipAll(string $targetObjectFormId): array {
                return $this->builder->build(76, compact('targetObjectFormId'));
            }

            // 78
            public function SendLycanthropyStateChanged(string $targetObjectFormId, bool $abIsWerewolf = true): array {
                return $this->builder->build(78, ['targetObjectFormId' => $targetObjectFormId, 'abIsWerewolf' => (int)$abIsWerewolf]);
            }

            // 79
            public function SendVampirismStateChanged(string $targetObjectFormId, bool $abIsVampire = true): array {
                return $this->builder->build(79, ['targetObjectFormId' => $targetObjectFormId, 'abIsVampire' => (int)$abIsVampire]);
            }

            // 43
            public function SetRelationshipRank(string $targetObjectFormId, string $akOther, int $aiRank): array {
                return $this->builder->build(43, compact('targetObjectFormId', 'akOther', 'aiRank'));
            }

            // 80
            public function RemoveFromAllFactions(string $targetObjectFormId): array {
                return $this->builder->build(80, compact('targetObjectFormId'));
            }

            public function EvaluatePackage(string $targetObjectFormId): array {
                return $this->builder->build(81, compact('targetObjectFormId'));
            }

            public function ShowBarterMenu(string $targetObjectFormId): array {
                return $this->builder->build(82, compact('targetObjectFormId'));
            }
        };

        $this->ObjectReference = new class($this) {
            private $builder;

            public function __construct($builder) {
                $this->builder = $builder;
            }

            // 100
            public function Activate(string $targetObjectFormId, string $akActivator): array {
                return $this->builder->build(100, compact('targetObjectFormId', 'akActivator'));
            }

            // 101
            public function AddItem(string $targetObjectFormId, string $akItemToAdd, int $aiCount = 1, bool $abSilent = false): array {
                return $this->builder->build(101, [
                    'targetObjectFormId' => $targetObjectFormId,
                    'akItemToAdd' => $akItemToAdd,
                    'aiCount' => $aiCount,
                    'abSilent' => (int)$abSilent
                ]);
            }

            // 102
            public function RemoveItem(string $targetObjectFormId, string $akItemToRemove, int $aiCount = 1, bool $abSilent = false): array {
                return $this->builder->build(102, [
                    'targetObjectFormId' => $targetObjectFormId,
                    'akItemToRemove' => $akItemToRemove,
                    'aiCount' => $aiCount,
                    'abSilent' => (int)$abSilent
                ]);
            }

            // 103
            public function Enable(string $targetObjectFormId, bool $abFadeIn = false): array {
                return $this->builder->build(103, ['targetObjectFormId' => $targetObjectFormId, 'abFadeIn' => (int)$abFadeIn]);
            }

            // 104
            public function Disable(string $targetObjectFormId, bool $abFadeOut = false): array {
                return $this->builder->build(104, ['targetObjectFormId' => $targetObjectFormId, 'abFadeOut' => (int)$abFadeOut]);
            }

            // 105
            public function Lock(string $targetObjectFormId, bool $abLock = true, bool $abAsOwner = false): array {
                return $this->builder->build(105, [
                    'targetObjectFormId' => $targetObjectFormId,
                    'abLock' => (int)$abLock,
                    'abAsOwner' => (int)$abAsOwner
                ]);
            }

            // 106
            public function Unlock(string $targetObjectFormId): array {
                return $this->builder->build(106, compact('targetObjectFormId'));
            }

            // 107
            public function SetOpen(string $targetObjectFormId, bool $abOpen = true): array {
                return $this->builder->build(107, ['targetObjectFormId' => $targetObjectFormId, 'abOpen' => (int)$abOpen]);
            }

            // 108
            public function AddToMap(string $targetObjectFormId, bool $abAllowFastTravel = true): array {
                return $this->builder->build(108, ['targetObjectFormId' => $targetObjectFormId, 'abAllowFastTravel' => (int)$abAllowFastTravel]);
            }

            // 109
            public function EnableFastTravel(string $targetObjectFormId, bool $abEnable = true): array {
                return $this->builder->build(109, ['targetObjectFormId' => $targetObjectFormId, 'abEnable' => (int)$abEnable]);
            }

            // 110
            public function SetLockLevel(string $targetObjectFormId, int $aiLockLevel): array {
                return $this->builder->build(110, compact('targetObjectFormId', 'aiLockLevel'));
            }

            // 111
            public function SetScale(string $targetObjectFormId, float $afScale): array {
                return $this->builder->build(111, compact('targetObjectFormId', 'afScale'));
            }

            // 112
            public function SetPosition(string $targetObjectFormId, float $afX, float $afY, float $afZ): array {
                return $this->builder->build(112, compact('targetObjectFormId', 'afX', 'afY', 'afZ'));
            }

            // 113
            public function SetAngle(string $targetObjectFormId, float $afXAngle, float $afYAngle, float $afZAngle): array {
                return $this->builder->build(113, compact('targetObjectFormId', 'afXAngle', 'afYAngle', 'afZAngle'));
            }

            // 114
            public function MoveTo(
                string $targetObjectFormId,
                string $akTarget,
                float $afXOffset = 0.0,
                float $afYOffset = 0.0,
                float $afZOffset = 0.0,
                bool $abMatchRotation = false
            ): array {
                return $this->builder->build(114, compact(
                    'targetObjectFormId', 'akTarget',
                    'afXOffset', 'afYOffset', 'afZOffset',
                    'abMatchRotation'
                ));
            }

            // 115
            public function ApplyHavokImpulse(string $targetObjectFormId, float $afX, float $afY, float $afZ, float $afMagnitude): array {
                return $this->builder->build(115, compact('targetObjectFormId', 'afX', 'afY', 'afZ', 'afMagnitude'));
            }

            // 116
            public function BlockActivation(string $targetObjectFormId, bool $abBlocked = true): array {
                return $this->builder->build(116, ['targetObjectFormId' => $targetObjectFormId, 'abBlocked' => (int)$abBlocked]);
            }

            // 117
            public function DamageObject(string $targetObjectFormId, float $afDamage): array {
                return $this->builder->build(117, compact('targetObjectFormId', 'afDamage'));
            }

            // 118
            public function DropObject(string $targetObjectFormId, string $akObject, int $aiCount = 1): array {
                return $this->builder->build(118, compact('targetObjectFormId', 'akObject', 'aiCount'));
            }

            // 119
            public function IgnoreFriendlyHits(string $targetObjectFormId, bool $abIgnore = true): array {
                return $this->builder->build(119, ['targetObjectFormId' => $targetObjectFormId, 'abIgnore' => (int)$abIgnore]);
            }

            // 120
            public function InterruptCast(string $targetObjectFormId): array {
                return $this->builder->build(120, compact('targetObjectFormId'));
            }

            // 121
            public function KnockAreaEffect(string $targetObjectFormId, float $afMagnitude, float $afRadius): array {
                return $this->builder->build(121, compact('targetObjectFormId', 'afMagnitude', 'afRadius'));
            }

            // 122
            public function PushActorAway(string $targetObjectFormId, string $akActorToPush, int $aiKnockbackDamage): array {
                return $this->builder->build(122, compact('targetObjectFormId', 'akActorToPush', 'aiKnockbackDamage'));
            }

            // 123
            public function SetActorOwner(string $targetObjectFormId, string $akActorBase): array {
                return $this->builder->build(123, compact('targetObjectFormId', 'akActorBase'));
            }

            // 124
            public function SetFactionOwner(string $targetObjectFormId, string $akFaction): array {
                return $this->builder->build(124, compact('targetObjectFormId', 'akFaction'));
            }

            // 125
            public function SetMotionType(string $targetObjectFormId, int $aiMotionType, bool $abAllowActivate = true): array {
                return $this->builder->build(125, [
                    'targetObjectFormId' => $targetObjectFormId,
                    'aiMotionType' => $aiMotionType,
                    'abAllowActivate' => (int)$abAllowActivate
                ]);
            }

            // 126
            public function SetNoFavorAllowed(string $targetObjectFormId, bool $abNoFavor = true): array {
                return $this->builder->build(126, ['targetObjectFormId' => $targetObjectFormId, 'abNoFavor' => (int)$abNoFavor]);
            }

            // 127
            public function SetDestroyed(string $targetObjectFormId, bool $abDestroyed = true): array {
                return $this->builder->build(127, ['targetObjectFormId' => $targetObjectFormId, 'abDestroyed' => (int)$abDestroyed]);
            }

            // 128
            public function ClearDestruction(string $targetObjectFormId): array {
                return $this->builder->build(128, compact('targetObjectFormId'));
            }

            // 129
            public function SendStealAlarm(string $targetObjectFormId, string $akThief): array {
                return $this->builder->build(129, compact('targetObjectFormId', 'akThief'));
            }

            // 130
            public function AddKeyIfNeeded(string $targetObjectFormId, string $ObjectWithNeededKey): array {
                return $this->builder->build(130, compact('targetObjectFormId', 'ObjectWithNeededKey'));
            }

            // 131
            public function PlaceAtMe(string $targetObjectFormId, string $akFormToPlace, int $aiCount = 1): array {
                return $this->builder->build(131, compact('targetObjectFormId', 'akFormToPlace', 'aiCount'));
            }
        };

        $this->FormList = new class($this) {
            private $builder;

            public function __construct($builder) {
                $this->builder = $builder;
            }

            // 200
            public function AddForm(string $targetObjectFormId, string $apForm): array {
                return $this->builder->build(200, compact('targetObjectFormId', 'apForm'));
            }

            // 201
            public function RemoveAddedForm(string $targetObjectFormId, string $apForm): array {
                return $this->builder->build(201, compact('targetObjectFormId', 'apForm'));
            }

            // 202
            public function Revert(string $targetObjectFormId): array {
                return $this->builder->build(202, compact('targetObjectFormId'));
            }
        };

        $this->EffectShader = new class($this) {
            private $builder;

            public function __construct($builder) {
                $this->builder = $builder;
            }

            // 300
            public function Play(string $targetObjectFormId, string $akObject, float $afDuration): array {
                return $this->builder->build(300, compact('targetObjectFormId', 'akObject','afDuration'));
            }

            // 301
            public function Stop(string $targetObjectFormId, string $akObject): array {
                return $this->builder->build(301, compact('targetObjectFormId','akObject'));
            }

        };


        $this->ActorUtil = new class($this) {
            private $builder;

            public function __construct($builder) {
                $this->builder = $builder;
            }

            // 400
            public function AddPackageOverride(string $targetObjectFormId, string $akActor, string $apForm,int $aiPriority): array {
                return $this->builder->build(400, compact('targetObjectFormId', 'akActor','apForm','aiPriority'));
            }

             // 401
            public function SetLinkedRef(string $targetObjectFormId, string $akActor, string $asRef): array {
                return $this->builder->build(401, compact('targetObjectFormId', 'akActor','asRef'));
            }

            // 490
            public function SpawnDoor(string $targetObjectFormId, string $akTargetRef, string $asName): array {
                return $this->builder->build(490, compact('targetObjectFormId', 'akTargetRef', 'asName'));
            }

            // 491
            public function PrintLinkedRef(string $targetObjectFormId, string $akActor): array {
                return $this->builder->build(491, compact('targetObjectFormId', 'akActor'));
            }

            // 492
            public function setDrivenByAIA(string $targetObjectFormId, string $akActor): array {
                return $this->builder->build(492, compact('targetObjectFormId', 'akActor'));
            }
        };

        $this->Faction = new class($this) {
            private $builder;

            public function __construct($builder) {
                $this->builder = $builder;
            }

            // 500
            public function CanPayCrimeGold(string $targetObjectFormId): array {
                return $this->builder->build(500, compact('targetObjectFormId'));
            }

            // 501
            public function GetCrimeGold(string $targetObjectFormId): array {
                return $this->builder->build(501, compact('targetObjectFormId'));
            }

            // 502
            public function GetCrimeGoldNonViolent(string $targetObjectFormId): array {
                return $this->builder->build(502, compact('targetObjectFormId'));
            }

            // 503
            public function GetCrimeGoldViolent(string $targetObjectFormId): array {
                return $this->builder->build(503, compact('targetObjectFormId'));
            }

            // 504
            public function GetInfamy(string $targetObjectFormId): array {
                return $this->builder->build(504, compact('targetObjectFormId'));
            }

            // 505
            public function GetInfamyNonViolent(string $targetObjectFormId): array {
                return $this->builder->build(505, compact('targetObjectFormId'));
            }

            // 506
            public function GetInfamyViolent(string $targetObjectFormId): array {
                return $this->builder->build(506, compact('targetObjectFormId'));
            }

            // 507
            public function GetReaction(string $targetObjectFormId, string $akOther): array {
                return $this->builder->build(507, compact('targetObjectFormId', 'akOther'));
            }

            // 508
            public function GetStolenItemValueCrime(string $targetObjectFormId): array {
                return $this->builder->build(508, compact('targetObjectFormId'));
            }

            // 509
            public function GetStolenItemValueNoCrime(string $targetObjectFormId): array {
                return $this->builder->build(509, compact('targetObjectFormId'));
            }

            // 510
            public function IsFactionInCrimeGroup(string $targetObjectFormId, string $akOther): array {
                return $this->builder->build(510, compact('targetObjectFormId', 'akOther'));
            }

            // 511
            public function IsPlayerExpelled(string $targetObjectFormId): array {
                return $this->builder->build(511, compact('targetObjectFormId'));
            }

            // 512
            public function ModCrimeGold(string $targetObjectFormId, int $aiAmount, bool $abViolent = false): array {
                return $this->builder->build(512, [
                    'targetObjectFormId' => $targetObjectFormId,
                    'aiAmount' => $aiAmount,
                    'abViolent' => (int)$abViolent
                ]);
            }

            // 513
            public function ModReaction(string $targetObjectFormId, string $akOther, int $aiAmount): array {
                return $this->builder->build(513, compact('targetObjectFormId', 'akOther', 'aiAmount'));
            }

            // 514
            public function PlayerPayCrimeGold(string $targetObjectFormId, bool $abRemoveStolenItems = false, bool $abGoToJail = false): array {
                return $this->builder->build(514, [
                    'targetObjectFormId' => $targetObjectFormId,
                    'abRemoveStolenItems' => (int)$abRemoveStolenItems,
                    'abGoToJail' => (int)$abGoToJail
                ]);
            }

            // 515
            public function SendAssaultAlarm(string $targetObjectFormId): array {
                return $this->builder->build(515, compact('targetObjectFormId'));
            }

            // 516
            public function SendPlayerToJail(string $targetObjectFormId, bool $abRemoveInventory = false, bool $abRealJail = false): array {
                return $this->builder->build(516, [
                    'targetObjectFormId' => $targetObjectFormId,
                    'abRemoveInventory' => (int)$abRemoveInventory,
                    'abRealJail' => (int)$abRealJail
                ]);
            }

            // 517
            public function SetAlly(string $targetObjectFormId, string $akOther, bool $abSelfIsFriendToOther = false, bool $abOtherIsFriendToSelf = false): array {
                return $this->builder->build(517, [
                    'targetObjectFormId' => $targetObjectFormId,
                    'akOther' => $akOther,
                    'abSelfIsFriendToOther' => (int)$abSelfIsFriendToOther,
                    'abOtherIsFriendToSelf' => (int)$abOtherIsFriendToSelf
                ]);
            }

            // 518
            public function SetCrimeGold(string $targetObjectFormId, int $aiGold): array {
                return $this->builder->build(518, compact('targetObjectFormId', 'aiGold'));
            }

            // 519
            public function SetCrimeGoldViolent(string $targetObjectFormId, int $aiGold): array {
                return $this->builder->build(519, compact('targetObjectFormId', 'aiGold'));
            }

            // 520
            public function SetEnemy(string $targetObjectFormId, string $akOther, bool $abSelfIsNeutralToOther = false, bool $abOtherIsNeutralToSelf = false): array {
                return $this->builder->build(520, [
                    'targetObjectFormId' => $targetObjectFormId,
                    'akOther' => $akOther,
                    'abSelfIsNeutralToOther' => (int)$abSelfIsNeutralToOther,
                    'abOtherIsNeutralToSelf' => (int)$abOtherIsNeutralToSelf
                ]);
            }

            // 521
            public function SetPlayerEnemy(string $targetObjectFormId, bool $abIsEnemy = true): array {
                return $this->builder->build(521, ['targetObjectFormId' => $targetObjectFormId, 'abIsEnemy' => (int)$abIsEnemy]);
            }

            // 522
            public function SetPlayerExpelled(string $targetObjectFormId, bool $abIsExpelled = true): array {
                return $this->builder->build(522, ['targetObjectFormId' => $targetObjectFormId, 'abIsExpelled' => (int)$abIsExpelled]);
            }

            // 523
            public function SetReaction(string $targetObjectFormId, string $akOther, int $aiNewValue): array {
                return $this->builder->build(523, compact('targetObjectFormId', 'akOther', 'aiNewValue'));
            }

            // 524
            public function GetVendorFactionContainer(string $targetObjectFormId): array {
                return $this->builder->build(524, compact('targetObjectFormId'));
            }
            
        };
    }
    
    public function build(int $cmdID, array $params): array {
        return array_merge(['cmdID' => $cmdID], $params);
    }

    public function getLoadOrderESP(): string{
        $hexFormId = $GLOBALS["db"]->fetchOne("select value from conf_opts where id='aiagent_rolemastered_faction'");
        if (!$hexFormId ) {
            throw new Exception("Configuration 'aiagent_rolemastered_faction' not set.");
        }
        //Extract ESP index from FormID
        $espIndexHex = substr($hexFormId["value"], 0, 2);
        return $espIndexHex;
    }

    public function send($cmd,$localts = null) {

        $strJson=json_encode($cmd);

        $GLOBALS["db"]->insert(
            'responselog',
            [
                'localts' => $localts??time(),
                'sent'    => 0,
                'actor'   => "rolemaster",
                'text'    => "",
                'action'  => 'rolecommand|ScriptProxy@'.$strJson,
                'tag'     => '',
            ]
        );
    }
}

// Use 
// $refId = "0x00000014"; // ? 20
// $cmd = $skyrim->Actor->ForceActorValue($refId, "Health", 100.0);