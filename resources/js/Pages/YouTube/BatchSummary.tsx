import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import React, { useState, useEffect, useRef } from 'react';
import ReactMarkdown from 'react-markdown';
import axios from 'axios';
import Dropdown from '@/Components/Dropdown';
import { formatLocalDate } from '@/utils/date';

interface TranscriptSegment {
    text: string;
    start: number;
    duration: number;
}

interface VideoResult {
    id?: number;
    videoUrl: string;
    title: string;
    thumbnail: string;
    transcript: TranscriptSegment[];
    summary?: string | null;
    summary_detailed?: string | null;
    channel_title?: string;
    duration?: string;
    published_at?: string;
    transcript_read_time?: string;
    summary_read_time?: string;
    duration_timestamp?: string;
    pdf_status?: 'pending' | 'processing' | 'completed' | 'failed';
    audio_status?: 'pending' | 'processing' | 'completed' | 'failed';
    pdf_url?: string;
    audio_url?: string;
    audio_duration?: number;
    summary_status?: 'pending' | 'processing' | 'completed' | 'failed' | null;
    transcript_translate_status?: 'pending' | 'processing' | 'completed' | 'failed' | null;
    summary_translate_status?: 'pending' | 'processing' | 'completed' | 'failed' | null;
    translations?: Record<string, any>;
}

interface BatchSummaryProps {
    auth: any;
    results: VideoResult[];
    isHistoryView?: boolean;
}

const SUPPORTED_LANGUAGES = [
    { code: 'en', label: 'English' },
    { code: 'es', label: 'Spanish' },
    { code: 'fr', label: 'French' },
    { code: 'de', label: 'German' },
    { code: 'it', label: 'Italian' },
    { code: 'pt', label: 'Portuguese' },
    { code: 'hi', label: 'Hindi' },
    { code: 'ja', label: 'Japanese' },
    { code: 'ko', label: 'Korean' },
    { code: 'zh', label: 'Chinese' },
];

const detectLanguage = (text: string): string | null => {
    if (!text || text.trim().length < 10) return null;
    const sample = text.substring(0, 1000).toLowerCase();

    const scores: Record<string, number> = {
        en: (sample.match(/\b(the|and|in|of|to|a|is|that|it|for|on)\b/g) || []).length,
        es: (sample.match(/\b(de|la|que|el|en|y|a|los|se|del|las)\b/g) || []).length,
        fr: (sample.match(/\b(de|la|le|et|les|des|en|un|du|une|que)\b/g) || []).length,
        de: (sample.match(/\b(der|die|und|in|den|von|zu|das|mit|sich)\b/g) || []).length,
        it: (sample.match(/\b(di|e|il|la|che|in|un|per|a|una|non)\b/g) || []).length,
        pt: (sample.match(/\b(de|a|que|o|e|do|da|em|um|para|com)\b/g) || []).length,
        zh: (sample.match(/(的|是|在|我|有|和|就|不|人|都)/g) || []).length * 2,
        ja: (sample.match(/(の|に|は|を|た|が|で|て|と|し)/g) || []).length * 2,
        ko: (sample.match(/(에|의|가|은|를|는|이|도|로|다)/g) || []).length * 2,
        hi: (sample.match(/\b(है|के|में|की|और|से|को|का|एक|पर)\b/g) || []).length,
    };

    let maxLanguage = 'en';
    let maxScore = 0;

    for (const [langKey, score] of Object.entries(scores)) {
        if (score > maxScore) {
            maxScore = score;
            maxLanguage = langKey;
        }
    }

    return maxScore >= 3 ? maxLanguage : null;
};

function groupTranscriptSegments(transcript: TranscriptSegment[]): TranscriptSegment[] {
    const grouped: TranscriptSegment[] = [];
    let current: TranscriptSegment | null = null;

    transcript.forEach((segment) => {
        if (!current) {
            current = { ...segment };
        } else {
            current.text = (current.text + ' ' + segment.text).replace(/\s+/g, ' ');
            current.duration += segment.duration;
        }

        const text = current.text.trim();
        const isSentenceEnd = /[.!?]['"]?$/.test(text);
        const isLengthOk = text.length > 50;
        const isDurationExceeded = current.duration > 30;

        if ((isSentenceEnd && isLengthOk) || isDurationExceeded) {
            grouped.push(current);
            current = null;
        }
    });

    if (current) {
        grouped.push(current);
    }

    return grouped;
}

const getTranscriptArray = (raw: any): any[] => {
    if (Array.isArray(raw)) return raw;
    if (raw && typeof raw === 'object') {
        if (Array.isArray(raw.transcript)) return raw.transcript;
        if (Array.isArray(raw.translatedTranscript)) return raw.translatedTranscript;
        // Handle cases where the object itself is what we want but might be mislabeled
        const possibleArray = Object.values(raw).find(val => Array.isArray(val));
        if (Array.isArray(possibleArray)) return possibleArray;
    }
    return [];
};

export default function BatchSummary({ auth, results, isHistoryView = false }: BatchSummaryProps) {
    const originalLanguages = React.useMemo(() => {
        const langs: Record<number, { transcript: string | null, summary: string | null }> = {};
        results.forEach(video => {
            if (!video.id) return;
            const tArray = getTranscriptArray(video.transcript);
            const tText = tArray.map(t => t.text || '').join(' ');
            const transcriptLang = detectLanguage(tText);
            langs[video.id] = {
                transcript: transcriptLang,
                summary: video.summary_detailed ? detectLanguage(video.summary_detailed) : transcriptLang
            };
        });
        return langs;
    }, [results]);

    const [localResults, setLocalResults] = useState<VideoResult[]>(() => {
        return results.map(video => {
            let initialTranscript = video.transcript;
            let initialSummaryDetailed = video.summary_detailed;

            if (typeof window !== 'undefined') {
                try {
                    const savedTranscriptLangs = JSON.parse(localStorage.getItem('transcriptLanguages') || '{}');
                    const savedSummaryLangs = JSON.parse(localStorage.getItem('summaryLanguages') || '{}');

                    const tLang = savedTranscriptLangs[video.id!];
                    if (tLang && video.translations?.[tLang]) {
                        const trans = video.translations[tLang];
                        // Robust transcript extraction from translations
                        if (Array.isArray(trans.transcript)) {
                            initialTranscript = trans.transcript;
                        } else if (trans.transcript && typeof trans.transcript === 'object') {
                            initialTranscript = trans.transcript.transcript || trans.transcript.translatedTranscript || trans.transcript;
                        }
                    }

                    const sLang = savedSummaryLangs[video.id!];
                    if (sLang && video.translations?.[sLang]?.summary_detailed) {
                        initialSummaryDetailed = video.translations[sLang].summary_detailed;
                    }
                } catch (e) {
                    console.error('Failed to parse language preferences from localStorage', e);
                }
            }

            return {
                ...video,
                transcript: initialTranscript,
                summary_detailed: initialSummaryDetailed
            };
        });
    });
    const [expandedIndices, setExpandedIndices] = useState<Record<number, boolean>>(isHistoryView ? { 0: true } : {});
    const [maximizedSections, setMaximizedSections] = useState<Record<number, 'transcript' | 'summary' | null>>({});

    const toggleMaximize = (index: number, section: 'transcript' | 'summary') => {
        setMaximizedSections(prev => ({
            ...prev,
            [index]: prev[index] === section ? null : section
        }));
    };

    const toggleExpand = (index: number) => {
        setExpandedIndices(prev => ({
            ...prev,
            [index]: !prev[index]
        }));
        setSearchTerm('');
    };

    const handleExpandAll = () => {
        const all: Record<number, boolean> = {};
        localResults.forEach((_, i) => { all[i] = true; });
        setExpandedIndices(all);
    };

    const handleCollapseAll = () => {
        setExpandedIndices({});
    };

    const [searchTerm, setSearchTerm] = useState('');
    const [showTimestamps, setShowTimestamps] = useState(true);

    const [copiedIndex, setCopiedIndex] = useState<number | null>(null);
    const [copiedSegmentKey, setCopiedSegmentKey] = useState<string | null>(null);
    const [processingId, setProcessingId] = useState<number | null>(null);
    const [isTranslating, setIsTranslating] = useState(false);
    const [translatingTranscriptId, setTranslatingTranscriptId] = useState<number | null>(null);
    const [generationError, setGenerationError] = useState<{ id: number, message: string } | null>(null);
    const [transcriptLanguages, setTranscriptLanguages] = useState<Record<number, string>>(() => {
        if (typeof window !== 'undefined') {
            try {
                const saved = localStorage.getItem('transcriptLanguages');
                if (saved) return JSON.parse(saved);
            } catch (e) {
                console.error('Failed to parse transcript languages from localStorage', e);
            }
        }
        return {};
    });

    const [summaryLanguages, setSummaryLanguages] = useState<Record<number, string>>(() => {
        if (typeof window !== 'undefined') {
            try {
                const saved = localStorage.getItem('summaryLanguages');
                if (saved) return JSON.parse(saved);
            } catch (e) {
                console.error('Failed to parse summary languages from localStorage', e);
            }
        }
        return {};
    });

    useEffect(() => {
        if (typeof window !== 'undefined') {
            localStorage.setItem('transcriptLanguages', JSON.stringify(transcriptLanguages));
        }
    }, [transcriptLanguages]);

    useEffect(() => {
        if (typeof window !== 'undefined') {
            localStorage.setItem('summaryLanguages', JSON.stringify(summaryLanguages));
        }
    }, [summaryLanguages]);

    // --- Polling & Download Logic ---
    const [downloading, setDownloading] = useState<Record<string, boolean>>({});
    const pollingRefs = useRef<Record<string, NodeJS.Timeout>>({});

    useEffect(() => {
        // Resume polling for any processing items on mount or when props update
        results.forEach(video => {
            if (video.id) {
                if (video.pdf_status === 'processing') {
                    setDownloading(prev => ({ ...prev, [`${video.id}-pdf`]: true }));
                    startPolling(video.id, 'pdf', `${video.id}-pdf`);
                }
                if (video.audio_status === 'processing') {
                    setDownloading(prev => ({ ...prev, [`${video.id}-audio`]: true }));
                    startPolling(video.id, 'audio', `${video.id}-audio`);
                }
                if (video.summary_status === 'processing') {
                    setDownloading(prev => ({ ...prev, [`${video.id}-summary`]: true }));
                    startPolling(video.id!, 'summary', `${video.id}-summary`);
                }
                if (video.transcript_translate_status === 'processing') {
                    startPolling(video.id, 'transcript_translate', `${video.id}-transcript_translate`);
                }
                if (video.summary_translate_status === 'processing') {
                    startPolling(video.id, 'summary_translate', `${video.id}-summary_translate`);
                }
            }
        });
    }, [results]);

    useEffect(() => {
        return () => {
            Object.values(pollingRefs.current).forEach(clearInterval);
        };
    }, []);

    const startPolling = (videoId: number, type: 'pdf' | 'audio' | 'summary' | 'transcript_translate' | 'summary_translate', key: string) => {
        if (pollingRefs.current[key]) return;
        pollingRefs.current[key] = setInterval(() => {
            checkStatus(videoId, type, key);
        }, 3000);
    };

    const checkStatus = async (videoId: number, type: 'pdf' | 'audio' | 'summary' | 'transcript_translate' | 'summary_translate', key: string) => {
        try {
            const response = await axios.get(route('video.status', videoId));
            let status;
            let url: string | undefined;

            if (type === 'summary') {
                status = response.data.summary_status;
            } else if (type === 'transcript_translate') {
                status = response.data.transcript_translate_status;
            } else if (type === 'summary_translate') {
                status = response.data.summary_translate_status;
            } else {
                status = type === 'pdf' ? response.data.pdf_status : response.data.audio_status;
                url = type === 'pdf' ? response.data.pdf_url : response.data.audio_url;
            }

            if (status === 'completed') {
                if (pollingRefs.current[key]) {
                    clearInterval(pollingRefs.current[key]);
                    const newRefs = { ...pollingRefs.current };
                    delete newRefs[key];
                    pollingRefs.current = newRefs;
                }
                setDownloading(prev => ({ ...prev, [key]: false }));
                if (type === 'summary' && processingId === videoId) {
                    setProcessingId(null);
                }

                if (type !== 'summary' && url) {
                    window.location.href = url;
                }
                // Reload data to get the new duration or summary without re-posting the form
                if (type === 'summary' || type === 'summary_translate' || type === 'transcript_translate') {
                    // Update local UI text with the newly fetched data from videoStatus
                    setLocalResults(prev => prev.map(v => {
                        if (v.id !== videoId) return v;

                        const updatedVideo = {
                            ...v,
                            summary: response.data.summary ?? v.summary,
                            summary_detailed: response.data.summary_detailed ?? v.summary_detailed,
                            translations: response.data.translations ?? v.translations,
                            summary_status: type === 'summary' ? 'completed' : v.summary_status,
                            summary_translate_status: type === 'summary_translate' ? 'completed' : v.summary_translate_status,
                            transcript_translate_status: type === 'transcript_translate' ? 'completed' : v.transcript_translate_status
                        };

                        // Determine which transcript and summary to show based on user's current selection
                        let displayTranscript = updatedVideo.transcript;
                        let displaySummary = updatedVideo.summary_detailed;

                        const tLang = transcriptLanguages[videoId];
                        if (tLang && updatedVideo.translations?.[tLang]) {
                            const trans = updatedVideo.translations[tLang];
                            if (Array.isArray(trans.transcript)) {
                                displayTranscript = trans.transcript;
                            } else if (trans.transcript && typeof trans.transcript === 'object') {
                                displayTranscript = trans.transcript.transcript || trans.transcript.translatedTranscript || trans.transcript;
                            }
                        }

                        const sLang = summaryLanguages[videoId];
                        if (sLang && updatedVideo.translations?.[sLang]?.summary_detailed) {
                            displaySummary = updatedVideo.translations[sLang].summary_detailed;
                        }

                        return {
                            ...updatedVideo,
                            transcript: displayTranscript,
                            summary_detailed: displaySummary
                        };
                    }));
                } else {
                    setLocalResults(prev => prev.map(v =>
                        v.id === videoId ? { ...v, [`${type}_status`]: 'completed', [`${type}_url`]: url } : v
                    ));
                }
            } else if (status === 'failed') {
                if (pollingRefs.current[key]) {
                    clearInterval(pollingRefs.current[key]);
                    const newRefs = { ...pollingRefs.current };
                    delete newRefs[key];
                    pollingRefs.current = newRefs;
                }
                setDownloading(prev => ({ ...prev, [key]: false }));
                if (type === 'summary' && processingId === videoId) {
                    setProcessingId(null);
                }
                alert(`Generation of ${type.toUpperCase()} failed.`);
            }
        } catch (error) {
            console.error('Status check failed:', error);
        }
    };

    const handleDownloadClick = async (videoId: number, type: 'pdf' | 'audio') => {
        const key = `${videoId}-${type}`;
        if (downloading[key]) return;

        setDownloading(prev => ({ ...prev, [key]: true }));

        try {
            // Check status first
            const statusRes = await axios.get(route('video.status', videoId));
            const status = type === 'pdf' ? statusRes.data.pdf_status : statusRes.data.audio_status;

            if (status === 'completed') {
                const url = type === 'pdf' ? statusRes.data.pdf_url : statusRes.data.audio_url;
                window.location.href = url;
                setDownloading(prev => ({ ...prev, [key]: false }));
            } else if (status === 'processing') {
                // Already processing, just poll
                startPolling(videoId, type, key);
            } else {
                // Trigger generation
                const triggerUrl = type === 'pdf' ? route('video.pdf', videoId) : route('video.audio', videoId);
                await axios.get(triggerUrl);
                startPolling(videoId, type, key);
            }
        } catch (error) {
            console.error("Download trigger failed", error);
            setDownloading(prev => ({ ...prev, [key]: false }));
            alert("Failed to start download.");
        }
    };


    const handleTranslate = async (videoId: number, language: string, originalLang?: string) => {
        const currentLang = transcriptLanguages[videoId] || originalLang;

        if (language === currentLang) {
            return;
        }

        if (originalLang && language === originalLang) {
            // Revert to original
            const originalVideo = results.find(v => v.id === videoId);
            if (originalVideo) {
                setLocalResults(prev => prev.map(v =>
                    v.id === videoId ? {
                        ...v,
                        transcript: originalVideo.transcript,
                        transcript_translate_status: 'completed'
                    } : v
                ));
                // Clear the override so it shows as original
                setTranscriptLanguages(prev => {
                    const next = { ...prev };
                    delete next[videoId];
                    return next;
                });
                return;
            }
        }

        console.log(`Starting translation for video ${videoId} to ${language}`);
        setGenerationError(null);
        setTranscriptLanguages(prev => ({ ...prev, [videoId]: language }));

        // Optimistically show processing status
        setLocalResults(prev => prev.map(v => v.id === videoId ? { ...v, transcript_translate_status: 'processing' } : v));

        try {
            const response = await axios.post(route('youtube.translate', videoId), { language });

            if (response.data.success) {
                if (response.data.status === 'processing') {
                    const key = `${videoId}-transcript_translate`;
                    startPolling(videoId, 'transcript_translate', key);
                    router.reload({ only: ['auth'] });
                } else if (response.data.status === 'completed') {
                    router.reload({ only: ['auth'] });
                    setLocalResults(prev => prev.map(v =>
                        v.id === videoId ? {
                            ...v,
                            transcript: response.data.transcript,
                            transcript_translate_status: 'completed'
                        } : v
                    ));
                }
            }
        } catch (error: any) {
            console.error("Translation failed", error);
            const msg = error.response?.data?.error || "Translation failed. Please try again.";
            setGenerationError({ id: videoId, message: msg });
            setLocalResults(prev => prev.map(v => v.id === videoId ? { ...v, transcript_translate_status: 'failed' } : v));
        }
    };

    const handleTranslateSummary = async (videoId: number, language: string, originalLang?: string) => {
        const currentLang = summaryLanguages[videoId] || originalLang;

        if (language === currentLang) {
            return;
        }

        if (originalLang && language === originalLang) {
            // Revert to original
            const originalVideo = results.find(v => v.id === videoId);
            if (originalVideo) {
                setLocalResults(prev => prev.map(v =>
                    v.id === videoId ? {
                        ...v,
                        summary_detailed: originalVideo.summary_detailed,
                        summary_translate_status: 'completed'
                    } : v
                ));
                // Clear the override
                setSummaryLanguages(prev => {
                    const next = { ...prev };
                    delete next[videoId];
                    return next;
                });
                return;
            }
        }

        console.log(`Starting summary translation for video ${videoId} to ${language}`);
        setGenerationError(null);
        setSummaryLanguages(prev => ({ ...prev, [videoId]: language }));

        // Optimistically show processing status
        setLocalResults(prev => prev.map(v => v.id === videoId ? { ...v, summary_translate_status: 'processing' } : v));

        try {
            const response = await axios.post(route('youtube.translate_summary', videoId), { language });

            if (response.data.success) {
                if (response.data.status === 'processing') {
                    const key = `${videoId}-summary_translate`;
                    startPolling(videoId, 'summary_translate', key);
                    router.reload({ only: ['auth'] });
                } else if (response.data.status === 'completed') {
                    router.reload({ only: ['auth'] });
                    setLocalResults(prev => prev.map(v =>
                        v.id === videoId ? {
                            ...v,
                            summary_detailed: response.data.summary_detailed,
                            summary_translate_status: 'completed'
                        } : v
                    ));
                }
            }
        } catch (error: any) {
            console.error("Summary translation failed", error);
            const msg = error.response?.data?.error || "Translation failed. Please try again.";
            setGenerationError({ id: videoId, message: msg });
            setLocalResults(prev => prev.map(v => v.id === videoId ? { ...v, summary_translate_status: 'failed' } : v));
        }
    };

    const copyToClipboard = async (text: string, index: number) => {
        try {
            await navigator.clipboard.writeText(text);
            handleCopySuccess(index);
        } catch (err) {
            try {
                const textArea = document.createElement("textarea");
                textArea.value = text;
                textArea.style.position = "fixed";
                textArea.style.left = "-9999px";
                textArea.style.top = "0";
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                const successful = document.execCommand('copy');
                document.body.removeChild(textArea);
                if (successful) {
                    handleCopySuccess(index);
                } else {
                    throw new Error('Fallback copy failed');
                }
            } catch (fallbackErr) {
                console.error('Failed to copy!', err, fallbackErr);
                alert('Failed to copy text. Please try manually.');
            }
        }
    };

    const handleCopySuccess = (index: number) => {
        setCopiedIndex(index);
        setTimeout(() => setCopiedIndex(null), 2000);
    };

    const downloadText = (filename: string, text: string) => {
        const element = document.createElement("a");
        const file = new Blob([text], { type: 'text/plain' });
        element.href = URL.createObjectURL(file);
        element.download = filename;
        document.body.appendChild(element);
        element.click();
    };

    const downloadMarkdown = (filename: string, video: VideoResult, type: 'transcript' | 'summary', includeTimestamps: boolean) => {
        let content = `# ${video.title}\n\n`;
        if (video.channel_title) content += `**Channel:** ${video.channel_title}\n`;
        if (video.videoUrl) content += `**URL:** ${video.videoUrl}\n`;
        content += `\n---\n\n`;

        if (type === 'summary') {
            content += `## Summary\n\n`;
            content += video.summary_detailed || video.summary || 'No summary available.';
        } else {
            content += `## Transcript\n\n`;
            content += getExportText(video, includeTimestamps);
        }

        downloadText(filename, content);
    };

    const getExportText = (video: VideoResult, includeTimestamps: boolean) => {
        const transcriptArray = Array.isArray(video.transcript) ? video.transcript : [];
        if (!includeTimestamps) {
            return transcriptArray.map(t => t.text || '').join(' ');
        }
        const segments = groupTranscriptSegments(transcriptArray);
        return segments.map(s => {
            const time = new Date(s.start * 1000).toISOString().substr(11, 8);
            return `[${time}] ${s.text} `;
        }).join('\n');
    };

    const copySegment = async (text: string, key: string) => {
        try {
            await navigator.clipboard.writeText(text);
            setCopiedSegmentKey(key);
            setTimeout(() => setCopiedSegmentKey(null), 2000);
        } catch (err) {
            try {
                const textArea = document.createElement("textarea");
                textArea.value = text;
                textArea.style.position = "fixed";
                textArea.style.left = "-9999px";
                textArea.style.top = "0";
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                const successful = document.execCommand('copy');
                document.body.removeChild(textArea);
                if (successful) {
                    setCopiedSegmentKey(key);
                    setTimeout(() => setCopiedSegmentKey(null), 2000);
                } else {
                    console.error('Fallback copy failed');
                }
            } catch (fallbackErr) {
                console.error('Failed to copy segment', err, fallbackErr);
            }
        }
    };

    const groupedResults = React.useMemo(() => {
        const groups: Record<string, { channel: string, videos: { video: VideoResult, originalIndex: number }[] }> = {};

        localResults.forEach((video, index) => {
            const isGenericTitle = !video.title || ['N/A', 'Unknown Video', 'Unknown Title', 'Unknown'].includes(video.title);
            const hasNoTranscript = !video.transcript || (Array.isArray(video.transcript) && video.transcript.length === 0);
            if (isGenericTitle && hasNoTranscript) return;

            const channel = video.channel_title || 'Other';
            if (!groups[channel]) {
                groups[channel] = { channel, videos: [] };
            }
            groups[channel].videos.push({ video, originalIndex: index });
        });

        return Object.values(groups).sort((a, b) => b.videos.length - a.videos.length);
    }, [localResults]);

    const overviewStats = React.useMemo(() => {
        let totalVideoSeconds = 0;
        let totalTranscriptMinutes = 0;
        let totalSummaryMinutes = 0;

        localResults.forEach(video => {
            if (video.duration_timestamp) {
                const parts = video.duration_timestamp.split(':').map(Number);
                if (parts.length === 3) {
                    totalVideoSeconds += (parts[0] * 3600) + (parts[1] * 60) + parts[2];
                } else if (parts.length === 2) {
                    totalVideoSeconds += (parts[0] * 60) + parts[1];
                }
            }

            if (video.transcript_read_time) {
                const match = video.transcript_read_time.match(/(\d+)/);
                if (match) totalTranscriptMinutes += parseInt(match[1], 10);
            }

            if (video.summary_read_time) {
                const match = video.summary_read_time.match(/(\d+)/);
                if (match) totalSummaryMinutes += parseInt(match[1], 10);
            }
        });

        const totalVideoMinutes = Math.ceil(totalVideoSeconds / 60);

        const formatVideoTime = () => {
             const h = Math.floor(totalVideoSeconds / 3600);
             const m = Math.floor((totalVideoSeconds % 3600) / 60);
             if (h > 0) return `${h}h ${m}m`;
             return `${m}m`;
        };

        const formatMinutesToHours = (totalMins: number) => {
             const h = Math.floor(totalMins / 60);
             const m = Math.floor(totalMins % 60);
             if (h > 0) return `${h}h ${m}m`;
             return `${m}m`;
        };

        const timeSavedMinutes = totalVideoMinutes - totalSummaryMinutes;

        return {
            videoTimeStr: formatVideoTime(),
            transcriptTimeStr: formatMinutesToHours(totalTranscriptMinutes),
            summaryTimeStr: formatMinutesToHours(totalSummaryMinutes),
            timeSavedStr: formatMinutesToHours(timeSavedMinutes > 0 ? timeSavedMinutes : 0)
        };
    }, [localResults]);

    return (
        <AuthenticatedLayout>
            <Head title={isHistoryView ? "Video Detail" : "Batch Results"} />

            {/* Hero */}
            <div className="relative overflow-hidden">
                <div className="absolute inset-0 bg-gradient-to-br from-indigo-50 via-purple-50 to-slate-100 dark:from-slate-900 dark:via-indigo-950 dark:to-slate-900" />
                <div className="absolute top-10 right-20 w-72 h-72 bg-indigo-500/10 dark:bg-indigo-500/15 rounded-full blur-3xl" />
                <div className="absolute bottom-0 left-10 w-80 h-80 bg-purple-500/5 dark:bg-purple-500/10 rounded-full blur-3xl" />

                <div className="relative py-10 lg:py-14">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <div>
                                <div className="inline-flex items-center rounded-full border border-indigo-500/30 bg-indigo-500/10 backdrop-blur-sm px-3 py-1 text-xs font-medium text-indigo-600 dark:text-indigo-300 mb-3">
                                    <svg className="w-3 h-3 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {isHistoryView ? 'Video Detail' : 'Batch Complete'}
                                </div>
                                <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-1">
                                    {isHistoryView ? localResults[0]?.title : `Processed ${localResults.length} Videos`}
                                </h1>
                            </div>
                            <div className="flex items-center gap-2">
                                {!isHistoryView && localResults.length > 1 && (
                                    <>
                                        <button 
                                            onClick={handleExpandAll}
                                            className="flex items-center gap-2 text-sm font-semibold text-gray-600 dark:text-white/70 hover:text-gray-900 dark:hover:text-white bg-white/50 dark:bg-white/10 backdrop-blur-sm rounded-lg px-5 py-2.5 ring-1 ring-gray-200 dark:ring-white/10 hover:ring-gray-300 dark:hover:ring-white/20 transition-all shadow-sm"
                                            title="Expand All"
                                        >
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                                            </svg>
                                            <span className="hidden sm:inline">Expand All</span>
                                        </button>
                                        <button 
                                            onClick={handleCollapseAll}
                                            className="flex items-center gap-2 text-sm font-semibold text-gray-600 dark:text-white/70 hover:text-gray-900 dark:hover:text-white bg-white/50 dark:bg-white/10 backdrop-blur-sm rounded-lg px-5 py-2.5 ring-1 ring-gray-200 dark:ring-white/10 hover:ring-gray-300 dark:hover:ring-white/20 transition-all shadow-sm"
                                            title="Collapse All"
                                        >
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 14h6m0 0v6m0-6l-7 7m17-11h-6m0 0V4m0 6l7-7M4 10h6m0 0V4m0 6l-7-7m17 11h-6m0 0v6m0-6l7 7" />
                                            </svg>
                                            <span className="hidden sm:inline">Collapse All</span>
                                        </button>
                                    </>
                                )}
                                {isHistoryView ? (
                                    <Link
                                        href={route('youtube.history')}
                                        className="flex items-center gap-2 text-sm font-medium text-gray-600 dark:text-white/70 hover:text-gray-900 dark:hover:text-white bg-white/50 dark:bg-white/10 backdrop-blur-sm rounded-lg px-4 py-2 ring-1 ring-gray-200 dark:ring-white/10 hover:ring-gray-300 dark:hover:ring-white/20 transition-all"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                        </svg>
                                        Back to History
                                    </Link>
                                ) : (
                                    <Link
                                        href={route('youtube.home')}
                                        className="flex items-center gap-2 text-sm font-semibold text-white bg-gradient-to-r from-indigo-500 to-purple-500 rounded-lg px-5 py-2.5 shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40 hover:from-indigo-600 hover:to-purple-600 transition-all"
                                    >
                                        Analyze More
                                    </Link>
                                )}
                            </div>
                        </div>

                        {/* Summary Stats Grid */}
                        {!isHistoryView && localResults.length > 0 && (
                            <div className="mt-10 grid grid-cols-2 lg:grid-cols-4 gap-4">
                                <div className="bg-white/60 dark:bg-gray-800/60 backdrop-blur-md rounded-2xl p-5 shadow-sm ring-1 ring-gray-200/50 dark:ring-gray-700/50">
                                    <div className="flex items-center gap-2 mb-2">
                                        <div className="p-1.5 bg-gray-100 dark:bg-gray-700 rounded-lg text-gray-500 dark:text-gray-400">
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <p className="text-sm font-medium text-gray-500 dark:text-gray-400">Total Video Time</p>
                                    </div>
                                    <p className="text-2xl font-bold text-gray-900 dark:text-white">
                                        {overviewStats.videoTimeStr}
                                    </p>
                                </div>
                                <div className="bg-white/60 dark:bg-gray-800/60 backdrop-blur-md rounded-2xl p-5 shadow-sm ring-1 ring-gray-200/50 dark:ring-gray-700/50">
                                    <div className="flex items-center gap-2 mb-2">
                                        <div className="p-1.5 bg-gray-100 dark:bg-gray-700 rounded-lg text-gray-500 dark:text-gray-400">
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                            </svg>
                                        </div>
                                        <p className="text-sm font-medium text-gray-500 dark:text-gray-400">Transcript Reading</p>
                                    </div>
                                    <p className="text-2xl font-bold text-gray-900 dark:text-white">
                                        {overviewStats.transcriptTimeStr}
                                    </p>
                                </div>
                                <div className="bg-white/60 dark:bg-gray-800/60 backdrop-blur-md rounded-2xl p-5 shadow-sm ring-1 ring-gray-200/50 dark:ring-gray-700/50">
                                    <div className="flex items-center gap-2 mb-2">
                                        <div className="p-1.5 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg text-indigo-500 dark:text-indigo-400">
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                                            </svg>
                                        </div>
                                        <p className="text-sm font-medium text-gray-500 dark:text-gray-400">Summary Reading</p>
                                    </div>
                                    <p className="text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                                        {overviewStats.summaryTimeStr}
                                    </p>
                                </div>
                                <div className="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl p-5 shadow-md shadow-indigo-500/20 ring-1 ring-white/20">
                                    <div className="flex items-center gap-2 mb-2">
                                        <div className="p-1.5 bg-white/20 rounded-lg text-white">
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <p className="text-sm font-medium text-white/90">Time Saved</p>
                                    </div>
                                    <p className="text-2xl font-bold text-white">
                                        {overviewStats.timeSavedStr}
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Content */}
            <div className="py-10">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="space-y-12">
                        {groupedResults.map((group, groupIdx) => (
                            <div key={groupIdx} className="space-y-6">
                                {/* Channel Header */}
                                <div className="flex items-center gap-4 pb-2 border-b border-gray-100 dark:border-gray-800">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 font-bold shadow-sm ring-1 ring-indigo-100 dark:ring-indigo-700/30">
                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h2 className="text-xl font-bold text-gray-900 dark:text-white">{group.channel}</h2>
                                        <p className="text-sm text-gray-500 dark:text-gray-400">{group.videos.length} {group.videos.length === 1 ? 'video' : 'videos'} analyzed</p>
                                    </div>
                                </div>

                                <div className="space-y-6">
                                    {group.videos.map(({ video, originalIndex: index }) => {
                                        const transcriptArray = getTranscriptArray(video.transcript);
                                        const fullText = transcriptArray.map(t => t.text || '').join(' ');
                                        const detectedTranscriptLang = originalLanguages[video.id!]?.transcript;
                                        const detectedSummaryLang = video.summary_detailed ? originalLanguages[video.id!]?.summary : detectedTranscriptLang;
                                        const displaySegments = groupTranscriptSegments(transcriptArray);
                                        const isExpanded = !!expandedIndices[index];

                                        // Statuses for this video
                                        const pdfStatus = video.pdf_status || 'pending';
                                        const audioStatus = video.audio_status || 'pending';
                                        const isPdfDownloading = downloading[`${video.id}-pdf`] || pdfStatus === 'processing';
                                        const isAudioDownloading = downloading[`${video.id}-audio`] || audioStatus === 'processing';

                                        return (
                                            <div key={index} className="bg-white dark:bg-gray-800 rounded-2xl ring-1 ring-gray-200/50 dark:ring-gray-700/50 shadow-sm overflow-hidden hover:shadow-md transition-shadow duration-300">
                                                {/* Collapsed Header */}
                                                <div className="p-6">
                                                    <div className="flex gap-5">
                                                        {video.thumbnail && (
                                                            <div className="relative w-48 rounded-xl overflow-hidden flex-shrink-0 group">
                                                                <img
                                                                    src={video.thumbnail}
                                                                    alt={video.title}
                                                                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                                                />
                                                                <a href={video.videoUrl} target="_blank" rel="noopener noreferrer" className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/40 backdrop-blur-[2px]" onClick={(e) => e.stopPropagation()}>
                                                                    <div className="w-10 h-10 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center ring-1 ring-white/30">
                                                                        <svg className="w-5 h-5 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                                                                            <path d="M8 5v14l11-7z" />
                                                                        </svg>
                                                                    </div>
                                                                </a>
                                                                {video.duration_timestamp && (
                                                                    <div className="absolute bottom-1.5 right-1.5 bg-black/80 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-[4px] pointer-events-none">
                                                                        {video.duration_timestamp}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        )}
                                                        <div className="flex-1 min-w-0">
                                                            <h4 className="text-lg font-bold text-gray-900 dark:text-white truncate">
                                                                {video.title || 'Unknown Title'}
                                                            </h4>

                                                            {/* Metadata Row */}
                                                            <div className="flex flex-wrap items-center gap-x-4 gap-y-2 mt-2 text-xs text-gray-500 dark:text-gray-400">
                                                                {video.published_at && (
                                                                    <div className="flex items-center gap-1">
                                                                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                                        </svg>
                                                                        Published: {formatLocalDate(video.published_at)}
                                                                    </div>
                                                                )}
                                                                {video.transcript_read_time && (
                                                                    <div className="flex items-center gap-1" title="Estimated time to read transcript">
                                                                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                                                        </svg>
                                                                        Transcript: {video.transcript_read_time}
                                                                    </div>
                                                                )}
                                                                {video.summary_read_time && (
                                                                    <div className="flex items-center gap-1 text-indigo-600 dark:text-indigo-400 font-medium" title="Estimated time to read summary">
                                                                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                                        </svg>
                                                                        Summary: {video.summary_read_time}
                                                                    </div>
                                                                )}
                                                            </div>

                                                            <div className="mt-3 flex items-center gap-3">
                                                                <span className="inline-flex items-center gap-1 rounded-full bg-green-50 dark:bg-green-900/20 px-2.5 py-1 text-xs font-medium text-green-700 dark:text-green-300 ring-1 ring-inset ring-green-600/20 dark:ring-green-500/20">
                                                                    <span className="w-1.5 h-1.5 rounded-full bg-green-500" />
                                                                    Success
                                                                </span>
                                                                <span className="text-xs text-gray-500 dark:text-gray-400">
                                                                    {getTranscriptArray(video.transcript).length} raw segments
                                                                </span>
                                                                {video.summary_detailed && (
                                                                    <span className="inline-flex items-center gap-1 rounded-full bg-indigo-50 dark:bg-indigo-900/20 px-2.5 py-1 text-xs font-medium text-indigo-700 dark:text-indigo-300 ring-1 ring-inset ring-indigo-600/20 dark:ring-indigo-500/20">
                                                                        <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                                        </svg>
                                                                        AI Summary
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </div>
                                                        <div className="flex-shrink-0 self-center">
                                                            <button
                                                                onClick={() => toggleExpand(index)}
                                                                className="rounded-xl p-2 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                            >
                                                                <svg className={`w-5 h-5 text-gray-400 transform transition-transform duration-200 ${isExpanded ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Expanded Content */}
                                                {isExpanded && (
                                                    <div className="border-t border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50 p-6">

                                                        {/* Controls Bar */}
                                                        <div className={`grid grid-cols-1 ${maximizedSections[index] ? '' : 'lg:grid-cols-2'} gap-6 mb-4`}>
                                                            <div className="flex flex-col sm:flex-row items-center gap-4">
                                                                <div className="relative flex-1 w-full">
                                                                    <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                                                        <svg className="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                                                        </svg>
                                                                    </div>
                                                                    <input
                                                                        type="text"
                                                                        className="block w-full rounded-xl border-0 py-2.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm dark:bg-gray-800 dark:ring-gray-600 dark:text-white"
                                                                        placeholder="Search transcript..."
                                                                        value={searchTerm}
                                                                        onChange={(e) => setSearchTerm(e.target.value)}
                                                                    />
                                                                </div>
                                                                <label className="inline-flex items-center cursor-pointer flex-shrink-0">
                                                                    <input
                                                                        type="checkbox"
                                                                        className="sr-only peer"
                                                                        checked={showTimestamps}
                                                                        onChange={(e) => setShowTimestamps(e.target.checked)}
                                                                    />
                                                                    <div className="relative w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-gray-600 peer-checked:bg-indigo-600"></div>
                                                                    <span className="ms-2 text-sm font-medium text-gray-700 dark:text-gray-300">Timestamps</span>
                                                                </label>
                                                            </div>
                                                            {!maximizedSections[index] && <div className="hidden lg:block"></div>}
                                                        </div>


                                                        {/* Content Grid */}
                                                        <div className={`grid grid-cols-1 ${maximizedSections[index] ? '' : 'lg:grid-cols-2'} gap-6`}>

                                                            {/* Transcript Column */}
                                                            <div className={`${maximizedSections[index] === 'summary' ? 'hidden' : ''} ${maximizedSections[index] === 'transcript' ? 'w-full' : ''} `}>
                                                                <div className="rounded-2xl bg-white dark:bg-gray-800 ring-1 ring-gray-300 dark:ring-gray-700/50 shadow-sm overflow-hidden flex flex-col max-h-[600px]">
                                                                    <div className="px-4 py-3 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center shrink-0">
                                                                        <div className="flex items-center gap-2">
                                                                            <svg className="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                                            </svg>
                                                                            <span className="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Full Transcript</span>
                                                                            <button
                                                                                onClick={() => toggleMaximize(index, 'transcript')}
                                                                                className="p-1 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg text-gray-400 hover:text-indigo-600 transition-colors"
                                                                                title={maximizedSections[index] === 'transcript' ? "Exit Full Screen" : "Full Screen"}
                                                                            >
                                                                                {maximizedSections[index] === 'transcript' ? (
                                                                                    <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 14h6v6M10 14l-6 6M20 10h-6V4M14 10l6-6" />
                                                                                    </svg>
                                                                                ) : (
                                                                                    <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                                                                                    </svg>
                                                                                )}
                                                                            </button>
                                                                        </div>
                                                                        <div className="flex items-center gap-2">
                                                                            {/* Translate Transcript Dropdown */}
                                                                            <div className="flex items-center gap-1 mr-2 px-2 py-1 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600">
                                                                                <span className="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase">Translate:</span>
                                                                                <select
                                                                                    className="bg-transparent border-0 text-[11px] font-bold text-indigo-600 dark:text-indigo-400 focus:ring-0 p-0 cursor-pointer"
                                                                                    onChange={(e) => handleTranslate(video.id!, e.target.value, detectedTranscriptLang || undefined)}
                                                                                    disabled={video.transcript_translate_status === 'processing'}
                                                                                    value={transcriptLanguages[video.id!] || (detectedTranscriptLang || "")}
                                                                                >
                                                                                    <option value="" disabled>Choose...</option>
                                                                                    {SUPPORTED_LANGUAGES.map(l => {
                                                                                        const isCurrent = (transcriptLanguages[video.id!] || detectedTranscriptLang) === l.code;
                                                                                        const isOriginal = detectedTranscriptLang === l.code;
                                                                                        return (
                                                                                            <option
                                                                                                key={l.code}
                                                                                                value={l.code}
                                                                                                disabled={isCurrent}
                                                                                            >
                                                                                                {isOriginal ? `${l.label} (Original)` : l.label}
                                                                                            </option>
                                                                                        );
                                                                                    })}
                                                                                </select>
                                                                                {video.transcript_translate_status === 'processing' && (
                                                                                    <svg className="animate-spin h-3 w-3 text-indigo-500 ml-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                                    </svg>
                                                                                )}
                                                                            </div>

                                                                            <button
                                                                                onClick={() => copyToClipboard(getExportText(video, showTimestamps), index)}
                                                                                className="flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 bg-white dark:bg-gray-800 rounded-lg px-2.5 py-1.5 ring-1 ring-gray-200 dark:ring-gray-600 hover:ring-indigo-300 dark:hover:ring-indigo-600 transition-all shadow-sm"
                                                                            >
                                                                                {copiedIndex === index ? (
                                                                                    <>
                                                                                        <svg className="w-3 h-3 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
                                                                                        Copied!
                                                                                    </>
                                                                                ) : 'Copy'}
                                                                            </button>
                                                                            <Dropdown>
                                                                                <Dropdown.Trigger>
                                                                                    <button className="text-xs font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 bg-white dark:bg-gray-800 rounded-lg px-2.5 py-1.5 ring-1 ring-gray-200 dark:ring-gray-600 hover:ring-indigo-300 dark:hover:ring-indigo-600 transition-all shadow-sm flex items-center gap-1">
                                                                                        Download
                                                                                        <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                                                                        </svg>
                                                                                    </button>
                                                                                </Dropdown.Trigger>
                                                                                <Dropdown.Content width="48" align="right">
                                                                                    <button
                                                                                        onClick={() => downloadText(`${video.title || 'transcript'}.txt`, getExportText(video, showTimestamps))}
                                                                                        className="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center justify-between"
                                                                                    >
                                                                                        <span>Text (.txt)</span>
                                                                                    </button>
                                                                                    <button
                                                                                        onClick={() => downloadMarkdown(`${video.title || 'transcript'}.md`, video, 'transcript', showTimestamps)}
                                                                                        className="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center justify-between"
                                                                                    >
                                                                                        <span>Markdown (.md)</span>
                                                                                    </button>
                                                                                    <div className="border-t border-gray-100 dark:border-gray-700 my-1"></div>
                                                                                    <button
                                                                                        onClick={() => handleDownloadClick(video.id!, 'pdf')}
                                                                                        disabled={downloading[`${video.id}-pdf`]}
                                                                                        className={`w-full text-left px-4 py-2 text-sm flex items-center justify-between
                                                                                ${downloading[`${video.id}-pdf`]
                                                                                                ? 'text-gray-400 cursor-not-allowed'
                                                                                                : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                                                                                            }`}
                                                                                    >
                                                                                        <div className="flex items-center gap-2">
                                                                                            <span>PDF (Full Report)</span>
                                                                                            {downloading[`${video.id}-pdf`] && (
                                                                                                <svg className="animate-spin h-3 w-3 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                                                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                                                </svg>
                                                                                            )}
                                                                                        </div>
                                                                                    </button>
                                                                                </Dropdown.Content>
                                                                            </Dropdown>
                                                                        </div>
                                                                    </div>
                                                                    <div className="p-4 overflow-y-auto">
                                                                        {showTimestamps ? (
                                                                            displaySegments.map((segment, i) => {
                                                                                const text = segment.text;
                                                                                if (!searchTerm) {
                                                                                    return (
                                                                                        <div key={i} className="group relative mb-3 text-gray-900 dark:text-gray-100 text-sm leading-relaxed hover:bg-gray-50 dark:hover:bg-gray-700/50 -mx-2 px-3 py-1 rounded-lg transition-colors pr-8">
                                                                                            <span className="font-mono text-xs text-indigo-700 dark:text-indigo-400 select-none mr-3 font-semibold">
                                                                                                {new Date(segment.start * 1000).toISOString().substr(11, 8)}
                                                                                            </span>
                                                                                            {text}
                                                                                            <button
                                                                                                onClick={() => copySegment(text, `${video.id || index}-${i}`)}
                                                                                                className="absolute right-2 top-2 opacity-0 group-hover:opacity-100 p-1 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-all"
                                                                                                title="Copy segment text"
                                                                                            >
                                                                                                {copiedSegmentKey === `${video.id || index}-${i}` ? (
                                                                                                    <svg className="w-4 h-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                                                                                    </svg>
                                                                                                ) : (
                                                                                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m2 4h6a2 2 0 012 2v6a2 2 0 01-2 2H10a2 2 0 01-2-2v-6a2 2 0 012-2z" />
                                                                                                    </svg>
                                                                                                )}
                                                                                            </button>
                                                                                        </div>
                                                                                    );
                                                                                }
                                                                                const parts = text.split(new RegExp(`(${searchTerm})`, 'gi'));
                                                                                return (
                                                                                    <div key={i} className="group relative mb-3 text-gray-900 dark:text-gray-100 text-sm leading-relaxed pr-8">
                                                                                        <span className="font-mono text-xs text-indigo-700 dark:text-indigo-400 select-none mr-3 font-semibold">
                                                                                            {new Date(segment.start * 1000).toISOString().substr(11, 8)}
                                                                                        </span>
                                                                                        {parts.map((part, pIdx) =>
                                                                                            part.toLowerCase() === searchTerm.toLowerCase() ? (
                                                                                                <span key={pIdx} className="bg-yellow-200 dark:bg-yellow-900 dark:text-yellow-100 rounded px-0.5 font-medium border-b-2 border-yellow-400">
                                                                                                    {part}
                                                                                                </span>
                                                                                            ) : part
                                                                                        )}
                                                                                        <button
                                                                                            onClick={() => copySegment(text, `${video.id || index}-${i}`)}
                                                                                            className="absolute right-2 top-2 opacity-0 group-hover:opacity-100 p-1 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-all"
                                                                                            title="Copy segment text"
                                                                                        >
                                                                                            {copiedSegmentKey === `${video.id || index}-${i}` ? (
                                                                                                <svg className="w-4 h-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                                                                                </svg>
                                                                                            ) : (
                                                                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m2 4h6a2 2 0 012 2v6a2 2 0 01-2 2H10a2 2 0 01-2-2v-6a2 2 0 012-2z" />
                                                                                                </svg>
                                                                                            )}
                                                                                        </button>
                                                                                    </div>
                                                                                );
                                                                            })
                                                                        ) : (
                                                                            <div className="text-gray-900 dark:text-gray-100 text-sm leading-relaxed whitespace-pre-wrap">
                                                                                {!searchTerm ? fullText : fullText.split(new RegExp(`(${searchTerm})`, 'gi')).map((part, pIdx) =>
                                                                                    part.toLowerCase() === searchTerm.toLowerCase() ? (
                                                                                        <span key={pIdx} className="bg-yellow-200 dark:bg-yellow-900 dark:text-yellow-100 rounded px-0.5 font-medium border-b-2 border-yellow-400">
                                                                                            {part}
                                                                                        </span>
                                                                                    ) : part
                                                                                )}
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                    <div className="px-4 py-2.5 bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-600 text-xs text-gray-500 dark:text-gray-400 font-mono text-center shrink-0">
                                                                        {fullText.split(/\s+/).filter(w => w.length > 0).length.toLocaleString()} words • {fullText.length.toLocaleString()} chars
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            {/* Summary Column */}
                                                            <div className={`${maximizedSections[index] === 'transcript' ? 'hidden' : ''} ${maximizedSections[index] === 'summary' ? 'w-full' : ''} `}>
                                                                <div className="h-full rounded-2xl bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 ring-1 ring-indigo-200/50 dark:ring-indigo-800/50 overflow-hidden flex flex-col max-h-[600px]">
                                                                    {video.summary_detailed ? (
                                                                        <>
                                                                            <div className="px-4 py-3 bg-indigo-100/50 dark:bg-indigo-900/40 border-b border-indigo-200 dark:border-indigo-800 shrink-0 flex justify-between items-center">
                                                                                <div className="flex items-center gap-2">
                                                                                    <svg className="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                                                    </svg>
                                                                                    <span className="text-xs font-semibold text-indigo-800 dark:text-indigo-300 uppercase tracking-wider">Summary</span>
                                                                                    <button
                                                                                        onClick={() => toggleMaximize(index, 'summary')}
                                                                                        className="p-1 hover:bg-indigo-200 dark:hover:bg-indigo-800 rounded-lg text-indigo-400 hover:text-indigo-700 transition-colors"
                                                                                        title={maximizedSections[index] === 'summary' ? "Exit Full Screen" : "Full Screen"}
                                                                                    >
                                                                                        {maximizedSections[index] === 'summary' ? (
                                                                                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 14h6v6M10 14l-6 6M20 10h-6V4M14 10l6-6" />
                                                                                            </svg>
                                                                                        ) : (
                                                                                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                                                                                            </svg>
                                                                                        )}
                                                                                    </button>
                                                                                </div>
                                                                                <div className="flex items-center gap-2">
                                                                                    <div className="flex items-center gap-1 mr-2 px-2 py-1 rounded-lg bg-white dark:bg-gray-800 border border-indigo-200 dark:border-indigo-700/50">
                                                                                        <span className="text-[10px] font-bold text-indigo-400 dark:text-indigo-500 uppercase">Translate:</span>
                                                                                        <select
                                                                                            className="bg-transparent border-0 text-[11px] font-bold text-indigo-600 dark:text-indigo-300 focus:ring-0 p-0 cursor-pointer"
                                                                                            onChange={(e) => handleTranslateSummary(video.id!, e.target.value, detectedSummaryLang || undefined)}
                                                                                            disabled={video.summary_translate_status === 'processing'}
                                                                                            value={summaryLanguages[video.id!] || (detectedSummaryLang || "")}
                                                                                        >
                                                                                            <option value="" disabled>Choose...</option>
                                                                                            {SUPPORTED_LANGUAGES.map(l => {
                                                                                                const isCurrent = (summaryLanguages[video.id!] || detectedSummaryLang) === l.code;
                                                                                                const isOriginal = detectedSummaryLang === l.code;
                                                                                                return (
                                                                                                    <option
                                                                                                        key={l.code}
                                                                                                        value={l.code}
                                                                                                        disabled={isCurrent}
                                                                                                    >
                                                                                                        {isOriginal ? `${l.label} (Original)` : l.label}
                                                                                                    </option>
                                                                                                );
                                                                                            })}
                                                                                        </select>
                                                                                        {video.summary_translate_status === 'processing' && (
                                                                                            <svg className="animate-spin h-3 w-3 text-indigo-500 ml-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                                            </svg>
                                                                                        )}
                                                                                    </div>

                                                                                    <button
                                                                                        onClick={() => {
                                                                                            const content = video.summary_detailed;
                                                                                            copySegment(content || '', `summary-detailed-${video.id || index}`);
                                                                                        }}
                                                                                        className="flex items-center gap-1 text-xs font-medium text-indigo-600 dark:text-indigo-300 bg-white dark:bg-gray-800 rounded-lg px-2.5 py-1.5 ring-1 ring-indigo-200 dark:ring-indigo-700/50 hover:ring-indigo-300 transition-all shadow-sm"
                                                                                    >
                                                                                        {copiedSegmentKey === `summary-detailed-${video.id || index}` ? (
                                                                                            <>
                                                                                                <svg className="w-3 h-3 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
                                                                                                Copied!
                                                                                            </>
                                                                                        ) : 'Copy'}
                                                                                    </button>
                                                                                    <Dropdown>
                                                                                        <Dropdown.Trigger>
                                                                                            <button className="text-xs font-medium text-indigo-600 dark:text-indigo-300 bg-white dark:bg-gray-800 rounded-lg px-2.5 py-1.5 ring-1 ring-indigo-200 dark:ring-indigo-700/50 hover:ring-indigo-300 transition-all shadow-sm flex items-center gap-1">
                                                                                                Download
                                                                                                <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                                                                                </svg>
                                                                                            </button>
                                                                                        </Dropdown.Trigger>
                                                                                        <Dropdown.Content width="48" align="right">
                                                                                            <button
                                                                                                onClick={() => downloadText(`${video.title || 'summary'}_summary.txt`, video.summary_detailed || video.summary || '')}
                                                                                                className="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center justify-between"
                                                                                            >
                                                                                                <span>Text (.txt)</span>
                                                                                            </button>
                                                                                            <button
                                                                                                onClick={() => downloadMarkdown(`${video.title || 'summary'}_summary.md`, video, 'summary', false)}
                                                                                                className="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center justify-between"
                                                                                            >
                                                                                                <span>Markdown (.md)</span>
                                                                                            </button>
                                                                                            <div className="border-t border-gray-100 dark:border-gray-700 my-1"></div>
                                                                                            <button
                                                                                                onClick={() => handleDownloadClick(video.id!, 'pdf')}
                                                                                                disabled={isPdfDownloading}
                                                                                                className={`w-full text-left px-4 py-2 text-sm flex items-center justify-between
                                                                                        ${isPdfDownloading
                                                                                                        ? 'text-gray-400 cursor-not-allowed'
                                                                                                        : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                                                                                                    }`}
                                                                                            >
                                                                                                <div className="flex items-center gap-2">
                                                                                                    <span>PDF (Full Report)</span>
                                                                                                    {isPdfDownloading && (
                                                                                                        <svg className="animate-spin h-3 w-3 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                                                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                                                        </svg>
                                                                                                    )}
                                                                                                </div>
                                                                                            </button>
                                                                                            <button
                                                                                                onClick={() => handleDownloadClick(video.id!, 'audio')}
                                                                                                disabled={isAudioDownloading}
                                                                                                className={`w-full text-left px-4 py-2 text-sm flex items-center justify-between
                                                                                        ${isAudioDownloading
                                                                                                        ? 'text-gray-400 cursor-not-allowed'
                                                                                                        : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                                                                                                    }`}
                                                                                            >
                                                                                                <div className="flex items-center gap-2">
                                                                                                    <span>Audio (.mp3)</span>
                                                                                                    {isAudioDownloading && (
                                                                                                        <svg className="animate-spin h-3 w-3 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                                                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                                                        </svg>
                                                                                                    )}
                                                                                                </div>
                                                                                            </button>
                                                                                        </Dropdown.Content>
                                                                                    </Dropdown>
                                                                                </div>
                                                                            </div>
                                                                            <div className="p-5 overflow-y-auto flex-1 prose prose-sm dark:prose-invert max-w-none text-gray-800 dark:text-gray-200 leading-relaxed">
                                                                                <ReactMarkdown>
                                                                                    {video.summary_detailed}
                                                                                </ReactMarkdown>
                                                                            </div>
                                                                            {(() => {
                                                                                const content = video.summary_detailed;
                                                                                if (!content) return null;
                                                                                const words = content.split(/\s+/).filter(w => w.length > 0).length;
                                                                                const chars = content.length;
                                                                                return (
                                                                                    <div className="px-4 py-2.5 bg-indigo-100/50 dark:bg-indigo-900/40 border-t border-indigo-200 dark:border-indigo-700 text-xs text-indigo-600 dark:text-indigo-400 font-mono text-center shrink-0">
                                                                                        {words.toLocaleString()} words • {chars.toLocaleString()} chars
                                                                                    </div>
                                                                                );
                                                                            })()}
                                                                        </>
                                                                    ) : (
                                                                        // Only show this block if there was an explicit error or if the user clicks retry
                                                                        // If status is pending/processing, show loading.
                                                                        // If status is failed, show the error UI.
                                                                        video.summary_status === 'failed' ? (
                                                                            <div className="flex flex-col items-center justify-center h-full p-8 text-center min-h-[300px]">
                                                                                <div className="bg-red-50 dark:bg-red-900/20 p-4 rounded-2xl shadow-sm ring-1 ring-red-200 dark:ring-red-800 mb-5">
                                                                                    <svg className="w-8 h-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                                                    </svg>
                                                                                </div>
                                                                                <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-1">
                                                                                    Summary Generation Failed
                                                                                </h3>
                                                                                <p className="text-sm text-gray-500 dark:text-gray-400 mb-6 max-w-xs">
                                                                                    We couldn't generate the summary automatically.
                                                                                </p>
                                                                                {/* Render the generic "Generate" button below */}
                                                                                <button
                                                                                    onClick={() => {
                                                                                        if (!video.id) return;
                                                                                        axios.post(route('youtube.summary.generate', video.id), {}, {
                                                                                        }).then(res => {
                                                                                            if (res.data.status === 'processing') {
                                                                                                setProcessingId(video.id!);
                                                                                                setGenerationError(null);
                                                                                                startPolling(video.id!, 'summary', `${video.id}-summary`);
                                                                                                router.reload({ only: ['auth'] });
                                                                                            }
                                                                                        }).catch(err => {
                                                                                            console.error("Generate Summary Failed", err);
                                                                                            setGenerationError({
                                                                                                id: video.id!,
                                                                                                message: err.response?.data?.error || "Failed to generate summary. Please try again."
                                                                                            });
                                                                                        });
                                                                                    }}
                                                                                    disabled={processingId === video.id}
                                                                                    className={`inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition-all ${processingId === video.id ? 'bg-indigo-400 cursor-not-allowed shadow-sm' : 'bg-gradient-to-r from-red-600 to-orange-600 shadow-red-500/25 hover:shadow-red-500/40 hover:from-red-700 hover:to-orange-700'}`}
                                                                                >
                                                                                    {processingId === video.id ? 'Retrying...' : 'Retry Generation'}
                                                                                </button>
                                                                                {generationError && generationError.id === video.id && (
                                                                                    <div className="mt-3 text-xs text-red-600 dark:text-red-400 text-center bg-red-50 dark:bg-red-900/20 px-3 py-2 rounded-lg ring-1 ring-red-200 dark:ring-red-800">
                                                                                        {generationError.message}
                                                                                    </div>
                                                                                )}
                                                                            </div>
                                                                        ) : (
                                                                            video.summary_status === 'processing' || processingId === video.id || video.summary_translate_status === 'processing' ? (
                                                                                <div className="flex flex-col items-center justify-center h-full p-8 text-center min-h-[300px]">
                                                                                    <svg className="animate-spin h-8 w-8 text-indigo-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                                    </svg>
                                                                                    <p className="text-sm text-gray-500 animate-pulse">{video.summary_translate_status === 'processing' ? 'Translating Summary...' : 'Generating Summary...'}</p>
                                                                                </div>
                                                                            ) : (
                                                                                <div className="flex flex-col items-center justify-center h-full p-8 text-center min-h-[300px]">
                                                                                    <div className="bg-white dark:bg-gray-800 p-4 rounded-2xl shadow-sm ring-1 ring-indigo-200/50 dark:ring-indigo-800/50 mb-5">
                                                                                        <svg className="w-8 h-8 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                                                        </svg>
                                                                                    </div>
                                                                                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-1">
                                                                                        Generate AI Summary
                                                                                    </h3>
                                                                                    <p className="text-sm text-gray-500 dark:text-gray-400 mb-6 max-w-xs">
                                                                                        Get a detailed summary of this video.
                                                                                    </p>
                                                                                    <button
                                                                                        onClick={() => {
                                                                                            if (!video.id) return;
                                                                                            setProcessingId(video.id!);
                                                                                            setGenerationError(null);
                                                                                            axios.post(route('youtube.summary.generate', video.id), {}, {})
                                                                                                .then(res => {
                                                                                                    if (res.data.status === 'processing') {
                                                                                                        startPolling(video.id!, 'summary', `${video.id}-summary`);
                                                                                                        router.reload({ only: ['auth'] });
                                                                                                    } else {
                                                                                                        setProcessingId(null);
                                                                                                    }
                                                                                                })
                                                                                                .catch(err => {
                                                                                                    console.error("Generate Summary Failed", err);
                                                                                                    setProcessingId(null);
                                                                                                    setGenerationError({
                                                                                                        id: video.id!,
                                                                                                        message: err.response?.data?.error || err.response?.data?.message || "Failed. Please try again."
                                                                                                    });
                                                                                                });
                                                                                        }}
                                                                                        disabled={processingId === video.id}
                                                                                        className={`inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition-all ${processingId === video.id ? 'bg-indigo-400 cursor-not-allowed shadow-sm' : 'bg-gradient-to-r from-indigo-600 to-purple-600 shadow-indigo-500/25 hover:shadow-indigo-500/40 hover:from-indigo-700 hover:to-purple-700'}`}
                                                                                    >
                                                                                        {processingId === video.id ? 'Generating...' : 'Generate Summary'}
                                                                                    </button>
                                                                                </div>
                                                                            )
                                                                        )
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
