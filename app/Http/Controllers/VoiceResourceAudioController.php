<?php

namespace App\Http\Controllers;

use App\Models\VoiceResource;
use App\Services\PrivateFiles;
use App\Services\VoiceSpaceAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class VoiceResourceAudioController extends Controller
{
    public function __invoke(Request $request, VoiceResource $resource, VoiceSpaceAccess $access, PrivateFiles $files): Response
    {
        $access->authorizeVoice($request->user(), $resource->voice);
        abort_unless($resource->audio_path, 404);
        $contents = $files->contents($resource->audio_path);
        $size = strlen($contents);
        $start = 0;
        $end = max(0, $size - 1);
        $status = 200;

        if ($range = $request->header('Range')) {
            if (! preg_match('/^bytes=(\d*)-(\d*)$/', $range, $matches) || $size === 0) {
                return response('', 416, ['Content-Range' => 'bytes */'.$size]);
            }
            if ($matches[1] === '' && $matches[2] !== '') {
                $length = min((int) $matches[2], $size);
                $start = $size - $length;
            } else {
                $start = (int) $matches[1];
                $end = $matches[2] === '' ? $end : min((int) $matches[2], $end);
            }
            if ($start > $end || $start >= $size) {
                return response('', 416, ['Content-Range' => 'bytes */'.$size]);
            }
            $status = 206;
            $contents = substr($contents, $start, $end - $start + 1);
        }

        $mime = in_array($resource->audio_mime, [
            'audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/wav', 'audio/x-wav',
            'audio/ogg', 'application/ogg', 'audio/aac',
        ], true) ? $resource->audio_mime : 'application/octet-stream';
        $name = preg_replace('/[\x00-\x1f\x7f]/u', '', basename(str_replace('\\', '/', $resource->audio_original_name ?: 'audio-'.$resource->id))) ?: 'audio';
        $headers = [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($contents),
            'Content-Disposition' => HeaderUtils::makeDisposition('inline', $name, str_replace('%', '_', Str::ascii($name)) ?: 'audio'),
            'Cache-Control' => 'private, no-store',
            'Accept-Ranges' => 'bytes',
            'X-Content-Type-Options' => 'nosniff',
        ];
        if ($status === 206) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        return response($contents, $status, $headers);
    }
}
