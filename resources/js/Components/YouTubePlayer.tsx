import React, { useEffect, useRef, useState, useImperativeHandle, forwardRef } from 'react';

export interface YouTubePlayerRef {
    seekTo: (seconds: number) => void;
}

interface YouTubePlayerProps {
    videoUrl: string;
    className?: string;
    onReady?: () => void;
}

const extractVideoId = (url: string): string | null => {
    const regExp = /^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#&?]*).*/;
    const match = url.match(regExp);
    return (match && match[7].length === 11) ? match[7] : null;
};

// Global callback for the YT API
declare global {
    interface Window {
        onYouTubeIframeAPIReady: () => void;
        YT: any;
    }
}

const YouTubePlayer = forwardRef<YouTubePlayerRef, YouTubePlayerProps>(({ videoUrl, className, onReady }, ref) => {
    const playerRef = useRef<HTMLDivElement>(null);
    const [player, setPlayer] = useState<any>(null);
    const [isApiReady, setIsApiReady] = useState(false);
    
    const videoId = extractVideoId(videoUrl);

    useEffect(() => {
        // Load the IFrame Player API code asynchronously
        if (!window.YT) {
            const tag = document.createElement('script');
            tag.src = 'https://www.youtube.com/iframe_api';
            const firstScriptTag = document.getElementsByTagName('script')[0];
            firstScriptTag.parentNode?.insertBefore(tag, firstScriptTag);

            window.onYouTubeIframeAPIReady = () => {
                setIsApiReady(true);
            };
        } else {
            setIsApiReady(true);
        }
    }, []);

    useEffect(() => {
        if (!isApiReady || !videoId || !playerRef.current) return;

        // If player already exists, load new video
        if (player) {
            player.loadVideoById(videoId);
            return;
        }

        const newPlayer = new window.YT.Player(playerRef.current, {
            videoId: videoId,
            playerVars: {
                playsinline: 1,
                rel: 0,
            },
            events: {
                onReady: (event: any) => {
                    setPlayer(event.target);
                    if (onReady) onReady();
                },
            },
        });

        // Cleanup
        return () => {
            if (newPlayer && newPlayer.destroy) {
                newPlayer.destroy();
            }
        };
    }, [isApiReady, videoId]);

    useImperativeHandle(ref, () => ({
        seekTo: (seconds: number) => {
            if (player && player.seekTo) {
                player.seekTo(seconds, true);
                player.playVideo();
            }
        }
    }));

    if (!videoId) {
        return (
            <div className={`flex items-center justify-center bg-gray-100 dark:bg-gray-800 text-gray-500 rounded-xl ${className}`}>
                Invalid YouTube URL
            </div>
        );
    }

    return (
        <div className={`relative w-full aspect-video rounded-xl overflow-hidden bg-black ${className || ''}`}>
            <div ref={playerRef} className="absolute inset-0 w-full h-full" />
        </div>
    );
});

YouTubePlayer.displayName = 'YouTubePlayer';

export default YouTubePlayer;
