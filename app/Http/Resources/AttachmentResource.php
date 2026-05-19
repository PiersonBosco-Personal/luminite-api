<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'uploaded_by'   => $this->uploaded_by,
            'folder_id'     => $this->folder_id,
            'filename'      => $this->filename,
            'original_name' => $this->original_name,
            'url'           => $this->url,
            'mime_type'     => $this->mime_type,
            'path'          => $this->path,
            'disk'          => $this->disk,
            'size'          => $this->size,
            'uploader'      => new UserResource($this->whenLoaded('uploader')),
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
