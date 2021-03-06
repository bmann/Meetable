<?php
namespace App\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Storage, Log;
use App\Events\WebmentionReceived;
use Image;
use App\Response, App\ResponsePhoto;

class WebmentionReceivedListener implements ShouldQueue {

    public function handle(WebmentionReceived $event) {
        Log::info('Webmention received: '.($event->response->source_url ?: $event->response->url));

        $response = $event->response;

        // If there is an author photo, download it and rewrite the URL
        if($response->author_photo) {
            $response->author_photo = $this->download($response, $response->author_photo, 150, 150);
            $response->save();
        }
    }

    private function download($response, $url, $w, $h) {
        Log::info('Downloading image '.$url);

        $filename = 'public/responses/'.$response->event->id.'/'.md5($url).'.jpg';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, \App\Helpers\HTTP::user_agent());
        $original_image = curl_exec($ch);

        if($original_image && curl_errno($ch) == 0) {
            try {
                $image = Image::make($original_image);
                $image->fit($w, $h);

                Storage::put($filename, $image->stream('jpg', 80));
                Storage::setVisibility($filename, 'public');

                $photo_url = Storage::url($filename);
                Log::info('  saved as '.$photo_url);

                return $photo_url;
            } catch(\Exception $e) {
                Log::error('  reading image at '.$url.' failed: '.$e->getMessage());
            }
        } else {
            Log::error('  download failed: '.curl_error($ch));
            return $url;
        }
    }

}
