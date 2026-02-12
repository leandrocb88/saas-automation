<x-mail::message>
# Daily YouTube Digest

Hi {{ $user->name }},

Here is your daily summary of new videos from your subscribed channels for {{ $date }}.

@foreach ($videos as $video)
<x-mail::panel>
## [{{ $video['title'] }}]({{ $video['videoUrl'] }})
![Thumbnail]({{ $video['thumbnail'] }})

**Summary:**
{{ Str::limit($video['summary'], 300) }}

<x-mail::button :url="$video['appUrl']">
View Full Summary
</x-mail::button>
</x-mail::panel>
@endforeach

<x-mail::button :url="route('youtube.digest.show', $shareToken)">
View Online
</x-mail::button>

<x-mail::button :url="route('youtube.digest')">
View All Digests
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
