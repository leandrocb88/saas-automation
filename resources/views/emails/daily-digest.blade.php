<x-mail::message>
<div style="text-align: center; padding-bottom: 20px;">
    <h1 style="color: #333; margin-bottom: 5px;">Daily Digest</h1>
    <p style="color: #666; font-size: 14px; margin-top: 0;">{{ $date }}</p>
</div>

@if(!empty($summaryMetrics))
<div style="background-color: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 25px; text-align: center; border: 1px solid #e9ecef;">
    <p style="margin: 0; font-size: 13px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Your Productivity Summary</p>
    <div style="display: flex; justify-content: center; gap: 20px; margin-top: 10px;">
        <div style="text-align: center;">
            <span style="font-size: 20px; font-weight: 700; color: #333; display: block;">{{ $summaryMetrics['total_videos'] }}</span>
            <span style="font-size: 12px; color: #888;">Videos</span>
        </div>
        <div style="text-align: center; border-left: 1px solid #ddd; padding-left: 20px;">
            <span style="font-size: 20px; font-weight: 700; color: #333; display: block;">{{ $summaryMetrics['total_duration'] }}</span>
            <span style="font-size: 12px; color: #888;">Watch Time</span>
        </div>
        <div style="text-align: center; border-left: 1px solid #ddd; padding-left: 20px;">
            <span style="font-size: 20px; font-weight: 700; color: #333; display: block;">{{ $summaryMetrics['read_time'] }}</span>
            <span style="font-size: 12px; color: #888;">Read Time</span>
        </div>
        <div style="text-align: center; border-left: 1px solid #ddd; padding-left: 20px;">
            <span style="font-size: 20px; font-weight: 700; color: #22c55e; display: block;">{{ $summaryMetrics['time_saved'] }}</span>
            <span style="font-size: 12px; color: #22c55e; font-weight: 600;">Saved ⚡</span>
        </div>
    </div>
</div>
@endif

Hi **{{ $user->name }}**,

Here are the latest updates from your subscribed channels.

@foreach ($videos as $video)
<x-mail::panel>
<div style="margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
    @if($video['channel_thumbnail'])
        <img src="{{ $video['channel_thumbnail'] }}" alt="" style="width: 24px; height: 24px; border-radius: 50%; vertical-align: middle;">
    @endif
    <span style="font-weight: 600; color: #444; font-size: 14px;">{{ $video['channel_name'] }}</span>
    @if(!empty($video['published_at']))
        <span style="color: #888; font-size: 12px;">• {{ $video['published_at'] }}</span>
    @endif
</div>

<a href="{{ $video['videoUrl'] }}" style="display: block; margin-bottom: 15px;">
    <img src="{{ $video['thumbnail'] }}" alt="{{ $video['title'] }}" style="width: 100%; border-radius: 8px; border: 1px solid #eee;">
</a>

<h2 style="font-size: 18px; margin-top: 0; margin-bottom: 10px;">
    <a href="{{ $video['videoUrl'] }}" style="color: #111; text-decoration: none;">{{ $video['title'] }}</a>
</h2>

<p style="color: #555; line-height: 1.6; font-size: 15px; margin-bottom: 20px;">
    {{ Str::limit($video['summary'], 350) }}
</p>

<x-mail::button :url="$video['appUrl']">
Read Full Summary
</x-mail::button>

</x-mail::panel>
@endforeach

<div style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee;">
    <p style="margin-bottom: 10px;">
        <a href="{{ route('youtube.digest.show', $shareToken) }}" style="color: #666; text-decoration: underline; font-size: 14px;">View this digest online</a>
    </p>
    <p style="color: #999; font-size: 12px;">
        You are receiving this email because you subscribed to daily digests on {{ config('app.name') }}.
        <br>
        <a href="{{ route('youtube.subscriptions') }}" style="color: #999; text-decoration: underline;">Manage Subscriptions</a>
    </p>
</div>

</x-mail::message>
