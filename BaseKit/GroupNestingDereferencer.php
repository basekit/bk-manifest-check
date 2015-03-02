<?php
namespace BaseKit;

use Exception;

class GroupNestingDereferencer
{
    const MAXIMUM_NESTING_LEVEL = 99;

    public function dereferenceGroupIncludes(array $groups)
    {
        foreach ($groups as $i => $group) {
            $groups[$i] = $this->dereferenceGroup($group, $groups);
        }

        return $groups;
    }

    private function dereferenceGroup($group, $groups, $level = 0)
    {
        if ($level > self::MAXIMUM_NESTING_LEVEL) {
            throw new Exception('Maximum template group nesting level reached');
        }

        $dirty = true;
        while ($dirty) {
            $dirty = false;
            foreach ($group['templates'] as $i => $name) {
                if (preg_match('/^group:(.*)$/', $name, $matches)) {
                    $dirty = true;
                    array_splice($group['templates'], $i, 1, $this->includeGroup($matches[1], $groups, $level));
                    break;
                }
            }
        }
        return $group;
    }

    private function includeGroup($name, $groups, $level)
    {
        $matchingGroups = array_filter(
            $groups,
            function ($group) use ($name) {
                return $group['name'] === $name;
            }
        );
        if (count($matchingGroups) > 0) {
            $group = current($matchingGroups);
            $group = $this->dereferenceGroup($group, $groups, $level + 1);
            return $group['templates'];
        }
    }
}
