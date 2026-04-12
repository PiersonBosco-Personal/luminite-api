<?php

use App\Models\User;

// it('returns 200 with empty data array on AI index', function () {
//     $user    = actingAsUser();
//     $project = createProject($user);

//     $this->getJson("/api/v1/projects/{$project->id}/ai")
//          ->assertStatus(200)
//          ->assertJson(['data' => []]);
// });

// it('returns 501 on AI store', function () {
//     $user    = actingAsUser();
//     $project = createProject($user);

//     $this->postJson("/api/v1/projects/{$project->id}/ai", ['prompt' => 'hello'])
//          ->assertStatus(501);
// });

// it('returns 403 on AI index when not a member', function () {
//     actingAsUser();
//     $other   = User::factory()->create();
//     $project = createProject($other);

//     $this->getJson("/api/v1/projects/{$project->id}/ai")->assertStatus(403);
// });

// it('returns 401 on AI index when unauthenticated', function () {
//     $this->getJson('/api/v1/projects/1/ai')->assertStatus(401);
// }); 
