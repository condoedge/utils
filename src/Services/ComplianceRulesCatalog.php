<?php

namespace App\Services;

/**
 * Static catalog of the 12 SISC compliance rules — business/exec angle.
 *
 * The actual rule logic lives in `app/ComplianceRules/`. This catalog only
 * carries the human-facing metadata (name, severity, what-it-checks, why,
 * how-it-triggers, how-to-resolve) keyed by translation keys so an admin can
 * understand from the UI what each rule does and why it matters.
 *
 * Updating a sheet : edit the i18n strings in `resources/lang/{fr,en}.json`
 * under the `compliance.rule.<slug>.*` namespace. Adding a rule : append an
 * entry below + matching translation keys.
 */
class ComplianceRulesCatalog
{
    public const CATEGORY_PERSON = 'person';
    public const CATEGORY_TEAM   = 'team';

    public const SEVERITY_ERROR   = 'error';
    public const SEVERITY_WARNING = 'warning';

    public static function all(): array
    {
        return [
            // ===== PERSON RULES (6) =====
            [
                'code'              => 'EnsureAllPeopleHaveValidAge',
                'category'          => self::CATEGORY_PERSON,
                'severity'          => self::SEVERITY_ERROR,
                'name_key'          => 'compliance.rule.valid-age.name',
                'short_desc_key'    => 'compliance.rule.valid-age.short',
                'why_matters_key'   => 'compliance.rule.valid-age.why',
                'how_triggers_key'  => 'compliance.rule.valid-age.triggers',
                'how_to_resolve_key'=> 'compliance.rule.valid-age.resolve',
            ],
            [
                'code'              => 'EnsureBackgroundCheckRule',
                'category'          => self::CATEGORY_PERSON,
                'severity'          => self::SEVERITY_WARNING,
                'name_key'          => 'compliance.rule.background-check.name',
                'short_desc_key'    => 'compliance.rule.background-check.short',
                'why_matters_key'   => 'compliance.rule.background-check.why',
                'how_triggers_key'  => 'compliance.rule.background-check.triggers',
                'how_to_resolve_key'=> 'compliance.rule.background-check.resolve',
            ],
            [
                'code'              => 'EnsureAllVolunteersHaveValidEmail',
                'category'          => self::CATEGORY_PERSON,
                'severity'          => self::SEVERITY_ERROR,
                'name_key'          => 'compliance.rule.volunteer-email.name',
                'short_desc_key'    => 'compliance.rule.volunteer-email.short',
                'why_matters_key'   => 'compliance.rule.volunteer-email.why',
                'how_triggers_key'  => 'compliance.rule.volunteer-email.triggers',
                'how_to_resolve_key'=> 'compliance.rule.volunteer-email.resolve',
            ],
            [
                'code'              => 'EnsurePersonValidAddresses',
                'category'          => self::CATEGORY_PERSON,
                'severity'          => self::SEVERITY_WARNING,
                'name_key'          => 'compliance.rule.person-address.name',
                'short_desc_key'    => 'compliance.rule.person-address.short',
                'why_matters_key'   => 'compliance.rule.person-address.why',
                'how_triggers_key'  => 'compliance.rule.person-address.triggers',
                'how_to_resolve_key'=> 'compliance.rule.person-address.resolve',
            ],
            [
                'code'              => 'EnsureScoutHasJustOneActiveTeam',
                'category'          => self::CATEGORY_PERSON,
                'severity'          => self::SEVERITY_ERROR,
                'name_key'          => 'compliance.rule.scout-single-team.name',
                'short_desc_key'    => 'compliance.rule.scout-single-team.short',
                'why_matters_key'   => 'compliance.rule.scout-single-team.why',
                'how_triggers_key'  => 'compliance.rule.scout-single-team.triggers',
                'how_to_resolve_key'=> 'compliance.rule.scout-single-team.resolve',
            ],
            [
                'code'              => 'EnsurePersonWithoutRolesOnInactiveTeam',
                'category'          => self::CATEGORY_PERSON,
                'severity'          => self::SEVERITY_ERROR,
                'name_key'          => 'compliance.rule.no-roles-inactive-team.name',
                'short_desc_key'    => 'compliance.rule.no-roles-inactive-team.short',
                'why_matters_key'   => 'compliance.rule.no-roles-inactive-team.why',
                'how_triggers_key'  => 'compliance.rule.no-roles-inactive-team.triggers',
                'how_to_resolve_key'=> 'compliance.rule.no-roles-inactive-team.resolve',
            ],
            [
                // Catalog-only for now — the matching rule class will be wired later.
                'code'              => 'EnsureTrainingCompletedOnTime',
                'category'          => self::CATEGORY_PERSON,
                'severity'          => self::SEVERITY_ERROR,
                'name_key'          => 'compliance.rule.training-on-time.name',
                'short_desc_key'    => 'compliance.rule.training-on-time.short',
                'why_matters_key'   => 'compliance.rule.training-on-time.why',
                'how_triggers_key'  => 'compliance.rule.training-on-time.triggers',
                'how_to_resolve_key'=> 'compliance.rule.training-on-time.resolve',
            ],

            // ===== TEAM RULES (6) =====
            [
                'code'              => 'EnsurePeopleRatioRule',
                'category'          => self::CATEGORY_TEAM,
                'severity'          => self::SEVERITY_WARNING,
                'name_key'          => 'compliance.rule.people-ratio.name',
                'short_desc_key'    => 'compliance.rule.people-ratio.short',
                'why_matters_key'   => 'compliance.rule.people-ratio.why',
                'how_triggers_key'  => 'compliance.rule.people-ratio.triggers',
                'how_to_resolve_key'=> 'compliance.rule.people-ratio.resolve',
            ],
            [
                'code'              => 'EnsureMaxPersonsPerRoleRule',
                'category'          => self::CATEGORY_TEAM,
                'severity'          => self::SEVERITY_ERROR,
                'name_key'          => 'compliance.rule.max-per-role.name',
                'short_desc_key'    => 'compliance.rule.max-per-role.short',
                'why_matters_key'   => 'compliance.rule.max-per-role.why',
                'how_triggers_key'  => 'compliance.rule.max-per-role.triggers',
                'how_to_resolve_key'=> 'compliance.rule.max-per-role.resolve',
            ],
            [
                'code'              => 'EnsureAtLeastOnePerson',
                'category'          => self::CATEGORY_TEAM,
                'severity'          => self::SEVERITY_WARNING,
                'name_key'          => 'compliance.rule.at-least-one-person.name',
                'short_desc_key'    => 'compliance.rule.at-least-one-person.short',
                'why_matters_key'   => 'compliance.rule.at-least-one-person.why',
                'how_triggers_key'  => 'compliance.rule.at-least-one-person.triggers',
                'how_to_resolve_key'=> 'compliance.rule.at-least-one-person.resolve',
            ],
            [
                'code'              => 'EnsureRoleIsAllowedInTeamLevel',
                'category'          => self::CATEGORY_TEAM,
                'severity'          => self::SEVERITY_ERROR,
                'name_key'          => 'compliance.rule.role-team-level.name',
                'short_desc_key'    => 'compliance.rule.role-team-level.short',
                'why_matters_key'   => 'compliance.rule.role-team-level.why',
                'how_triggers_key'  => 'compliance.rule.role-team-level.triggers',
                'how_to_resolve_key'=> 'compliance.rule.role-team-level.resolve',
            ],
            [
                'code'              => 'EnsureTeamHasPeopleOfAllRoles',
                'category'          => self::CATEGORY_TEAM,
                'severity'          => self::SEVERITY_ERROR,
                'name_key'          => 'compliance.rule.all-mandatory-roles.name',
                'short_desc_key'    => 'compliance.rule.all-mandatory-roles.short',
                'why_matters_key'   => 'compliance.rule.all-mandatory-roles.why',
                'how_triggers_key'  => 'compliance.rule.all-mandatory-roles.triggers',
                'how_to_resolve_key'=> 'compliance.rule.all-mandatory-roles.resolve',
            ],
            [
                'code'              => 'EnsureTeamValidAddresses',
                'category'          => self::CATEGORY_TEAM,
                'severity'          => self::SEVERITY_WARNING,
                'name_key'          => 'compliance.rule.team-address.name',
                'short_desc_key'    => 'compliance.rule.team-address.short',
                'why_matters_key'   => 'compliance.rule.team-address.why',
                'how_triggers_key'  => 'compliance.rule.team-address.triggers',
                'how_to_resolve_key'=> 'compliance.rule.team-address.resolve',
            ],
        ];
    }

    public static function byCategory(): array
    {
        $out = [self::CATEGORY_PERSON => [], self::CATEGORY_TEAM => []];
        foreach (self::all() as $rule) {
            $out[$rule['category']][] = $rule;
        }
        // Severity first (ERROR before WARNING), then alphabetical by display name.
        $severityWeight = fn ($sev) => $sev === self::SEVERITY_ERROR ? 0 : 1;
        foreach ($out as $cat => &$rules) {
            usort($rules, function ($a, $b) use ($severityWeight) {
                $cmp = $severityWeight($a['severity']) <=> $severityWeight($b['severity']);
                return $cmp !== 0 ? $cmp : strcmp(__($a['name_key']), __($b['name_key']));
            });
        }
        unset($rules);
        return $out;
    }

    public static function find(string $ruleCode): ?array
    {
        foreach (self::all() as $rule) {
            if ($rule['code'] === $ruleCode) {
                return $rule;
            }
        }
        return null;
    }
}
