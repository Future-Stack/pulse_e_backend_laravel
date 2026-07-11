// Attach a goal to a profile
$profile->healthGoals()->attach($goalId);

// Detach a goal
$profile->healthGoals()->detach($goalId);

// Sync multiple goals
$profile->healthGoals()->sync([1, 2, 3]);

// Get all goals for a profile
$profile->healthGoals;

// Get all profiles for a goal
$goal->profiles;
