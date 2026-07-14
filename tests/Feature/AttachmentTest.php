<?php

use App\Models\AttachmentFolder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

// ── Setup helpers ────────────────────────────────────────────────────────────

/**
 * Returns ['project' => Project, 'owner' => User, 'member' => User, 'outsider' => User]
 * with the current acting user set to $actAs ('owner' or 'member').
 */
function attachmentTestSetup(string $actAs = 'member'): array
{
    Storage::fake('local');

    $owner    = User::factory()->create();
    $member   = User::factory()->create();
    $outsider = User::factory()->create();
    $project  = createProject($owner);
    addMemberToProject($project, $member);

    Sanctum::actingAs($$actAs);

    return compact('project', 'owner', 'member', 'outsider');
}

// ── Upload ───────────────────────────────────────────────────────────────────

it('member can upload a file to a project', function () {
    ['project' => $project] = attachmentTestSetup('member');

    $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

    $response = $this->postJson("/api/v1/projects/{$project->id}/attachments", [
        'file' => $file,
    ]);

    $response->assertStatus(201)
             ->assertJsonPath('data.original_name', 'report.pdf');

    Storage::disk('local')->assertExists($response->json('data.path'));
});

it('returns a clean 500 and records nothing when the file cannot be written', function () {
    ['project' => $project] = attachmentTestSetup('member');

    // Force the disk write to report failure (full disk / permissions).
    $disk = Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
    $disk->shouldReceive('putFileAs')->once()->andReturn(false);
    Storage::shouldReceive('disk')->with('local')->andReturn($disk);

    $file = UploadedFile::fake()->create('report.pdf', 100);

    $this->postJson("/api/v1/projects/{$project->id}/attachments", ['file' => $file])
         ->assertStatus(500)
         ->assertJsonPath('data', null)
         ->assertJsonFragment(['message' => 'The file could not be saved. Please try again.']);

    expect(\App\Models\Attachment::count())->toBe(0);
});

it('deletes the orphaned file when the database insert fails', function () {
    ['project' => $project] = attachmentTestSetup('member');

    // File write succeeds on the fake disk, but the DB step blows up. Eloquent
    // uses its own connection resolver, so mocking the DB facade only diverts
    // the controller's explicit DB::transaction() call.
    DB::shouldReceive('transaction')->once()->andThrow(new RuntimeException('db down'));

    $file = UploadedFile::fake()->create('report.pdf', 100);

    $this->postJson("/api/v1/projects/{$project->id}/attachments", ['file' => $file])
         ->assertStatus(500);

    expect(\App\Models\Attachment::count())->toBe(0);
    Storage::disk('local')->assertDirectoryEmpty("attachments/{$project->id}");
});

it('outsider cannot upload to a project', function () {
    ['project' => $project, 'outsider' => $outsider] = attachmentTestSetup('member');

    Sanctum::actingAs($outsider);
    $file = UploadedFile::fake()->create('report.pdf', 100);

    $this->postJson("/api/v1/projects/{$project->id}/attachments", ['file' => $file])
         ->assertStatus(403);
});

it('file over 50 MB is rejected', function () {
    ['project' => $project] = attachmentTestSetup('member');

    // 51201 KB = ~50 MB + 1 KB — exceeds the max:51200 rule
    $file = UploadedFile::fake()->create('big.zip', 51201);

    $this->postJson("/api/v1/projects/{$project->id}/attachments", ['file' => $file])
         ->assertStatus(422);
});

// ── Download / stream ────────────────────────────────────────────────────────

it('member can download an attachment', function () {
    ['project' => $project] = attachmentTestSetup('member');

    $file       = UploadedFile::fake()->create('doc.txt', 1, 'text/plain');
    $uploadResp = $this->postJson("/api/v1/projects/{$project->id}/attachments", ['file' => $file]);
    $uploadResp->assertStatus(201);

    $attachmentId = $uploadResp->json('data.id');

    $this->get("/api/v1/attachments/{$attachmentId}")
         ->assertStatus(200);
});

it('outsider cannot download an attachment', function () {
    ['project' => $project, 'outsider' => $outsider] = attachmentTestSetup('member');

    $file       = UploadedFile::fake()->create('doc.txt', 1);
    $uploadResp = $this->postJson("/api/v1/projects/{$project->id}/attachments", ['file' => $file]);
    $uploadResp->assertStatus(201);

    $attachmentId = $uploadResp->json('data.id');

    Sanctum::actingAs($outsider);

    $this->get("/api/v1/attachments/{$attachmentId}")
         ->assertStatus(403);
});

// ── Delete ───────────────────────────────────────────────────────────────────

it('uploader can delete their own attachment', function () {
    ['project' => $project] = attachmentTestSetup('member');

    $file       = UploadedFile::fake()->create('doc.txt', 1);
    $uploadResp = $this->postJson("/api/v1/projects/{$project->id}/attachments", ['file' => $file]);
    $uploadResp->assertStatus(201);

    $attachmentId = $uploadResp->json('data.id');

    $this->deleteJson("/api/v1/attachments/{$attachmentId}")
         ->assertStatus(200);

    $this->assertDatabaseMissing('attachments', ['id' => $attachmentId]);
});

it('non-uploader member cannot delete another member\'s attachment', function () {
    ['project' => $project, 'owner' => $owner, 'member' => $member] = attachmentTestSetup('owner');

    // Owner uploads a file
    $file       = UploadedFile::fake()->create('doc.txt', 1);
    $uploadResp = $this->postJson("/api/v1/projects/{$project->id}/attachments", ['file' => $file]);
    $uploadResp->assertStatus(201);

    $attachmentId = $uploadResp->json('data.id');

    // Member (non-uploader, non-owner) tries to delete
    Sanctum::actingAs($member);

    $this->deleteJson("/api/v1/attachments/{$attachmentId}")
         ->assertStatus(403);
});

it('project owner can delete any attachment', function () {
    ['project' => $project, 'owner' => $owner] = attachmentTestSetup('member');

    $file       = UploadedFile::fake()->create('doc.txt', 1);
    $uploadResp = $this->postJson("/api/v1/projects/{$project->id}/attachments", ['file' => $file]);
    $uploadResp->assertStatus(201);

    $attachmentId = $uploadResp->json('data.id');

    Sanctum::actingAs($owner);

    $this->deleteJson("/api/v1/attachments/{$attachmentId}")
         ->assertStatus(200);
});

// ── Folders ──────────────────────────────────────────────────────────────────

it('member can create an attachment folder', function () {
    ['project' => $project] = attachmentTestSetup('member');

    $this->postJson("/api/v1/projects/{$project->id}/attachment-folders", ['name' => 'Design'])
         ->assertStatus(201)
         ->assertJsonPath('data.name', 'Design');
});

it('deleting a folder moves its files to the project root (null folder_id)', function () {
    ['project' => $project, 'owner' => $owner, 'member' => $member] = attachmentTestSetup('member');

    // Create a folder directly (owner is creator)
    $folder = AttachmentFolder::create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'name'       => 'To Delete',
        'position'   => 0,
    ]);

    // Upload a file into that folder
    $file       = UploadedFile::fake()->create('doc.txt', 1);
    $uploadResp = $this->postJson("/api/v1/projects/{$project->id}/attachments", [
        'file'      => $file,
        'folder_id' => $folder->id,
    ]);
    $uploadResp->assertStatus(201);

    $attachmentId = $uploadResp->json('data.id');

    // Owner deletes the folder
    Sanctum::actingAs($owner);

    $this->deleteJson("/api/v1/projects/{$project->id}/attachment-folders/{$folder->id}")
         ->assertStatus(200);

    $this->assertDatabaseHas('attachments', ['id' => $attachmentId, 'folder_id' => null]);
    $this->assertDatabaseMissing('attachment_folders', ['id' => $folder->id]);
});
