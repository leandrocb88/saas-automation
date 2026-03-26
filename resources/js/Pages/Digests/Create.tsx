import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { PageProps } from '@/types';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import { FormEventHandler } from 'react';
import Checkbox from '@/Components/Checkbox';

interface Channel {
    id: number;
    name: string;
    thumbnail_url: string;
    is_paused: boolean;
}

interface Props extends PageProps {
    availableChannels: Channel[];
}

export default function Create({ auth, availableChannels }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        frequency: 'daily',
        scheduled_at: '08:00',
        day_of_week: 'mon',
        mode: 'channels',
        search_term: '',
        channel_ids: [] as number[],
        custom_prompt: '',
        global_summary_prompt: '',
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
        video_types: ['videos'] as string[],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('digests.store'));
    };

    const toggleChannel = (id: number) => {
        const current = new Set(data.channel_ids);
        if (current.has(id)) {
            current.delete(id);
        } else {
            current.add(id);
        }
        setData('channel_ids', Array.from(current));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Create Digest" />

            {/* Hero Header */}
            <div className="relative bg-gradient-to-br from-indigo-50 via-purple-50 to-indigo-100 dark:from-indigo-900 dark:via-purple-900 dark:to-indigo-900 overflow-hidden">
                <div className="absolute inset-0 overflow-hidden pointer-events-none">
                    <div className="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-indigo-500/10 dark:bg-white/5 blur-3xl" />
                    <div className="absolute -bottom-32 -left-32 w-80 h-80 rounded-full bg-purple-500/10 dark:bg-purple-400/10 blur-3xl" />
                </div>

                <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
                    <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-6">
                        <div>
                            <Link href={route('digests.index')} className="inline-flex items-center text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 mb-4 transition-colors">
                                <svg className="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Back to Digests
                            </Link>

                            <h1 className="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-white tracking-tight">
                                Create New Digest
                            </h1>
                            <p className="mt-2 text-indigo-900 dark:text-indigo-100 text-lg max-w-xl">
                                Set up a new automatic summary of your favorite channels or topics.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
                <div className="bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm rounded-[2rem] border border-gray-200/50 dark:border-gray-700/50 shadow-xl shadow-gray-200/20 dark:shadow-none overflow-hidden">
                    <div className="p-8">
                        <form onSubmit={submit} className="space-y-6">

                            <div>
                                <InputLabel htmlFor="name" value="Digest Name" className="text-gray-700 dark:text-gray-300 font-medium ml-1" />
                                <TextInput
                                    id="name"
                                    className="mt-2 block w-full bg-white/50 dark:bg-gray-900/50 backdrop-blur-sm border-gray-200/80 dark:border-gray-700/80 rounded-xl focus:ring-indigo-500/30 focus:border-indigo-500 shadow-sm transition-all"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                    isFocused
                                    placeholder="e.g., Morning Tech News"
                                />
                                <InputError className="mt-2" message={errors.name} />
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel htmlFor="frequency" value="Frequency" className="text-gray-700 dark:text-gray-300 font-medium ml-1" />
                                    <select
                                        id="frequency"
                                        className="mt-2 block w-full bg-white/50 dark:bg-gray-900/50 backdrop-blur-sm border-gray-200/80 dark:border-gray-700/80 rounded-xl focus:ring-indigo-500/30 focus:border-indigo-500 text-gray-900 dark:text-gray-100 shadow-sm transition-all"
                                        value={data.frequency}
                                        onChange={(e) => setData('frequency', e.target.value)}
                                    >
                                        <option value="daily">Daily</option>
                                        <option value="weekly">Weekly</option>
                                    </select>
                                    <InputError className="mt-2" message={errors.frequency} />
                                </div>

                                <div>
                                    <InputLabel htmlFor="scheduled_at" value="Time" className="text-gray-700 dark:text-gray-300 font-medium ml-1" />
                                    <TextInput
                                        id="scheduled_at"
                                        type="time"
                                        className="mt-2 block w-full bg-white/50 dark:bg-gray-900/50 backdrop-blur-sm border-gray-200/80 dark:border-gray-700/80 rounded-xl focus:ring-indigo-500/30 focus:border-indigo-500 shadow-sm transition-all"
                                        value={data.scheduled_at}
                                        onChange={(e) => setData('scheduled_at', e.target.value)}
                                        required
                                    />
                                    <InputError className="mt-2" message={errors.scheduled_at} />
                                </div>
                            </div>

                            {data.frequency === 'weekly' && (
                                <div className="animate-in fade-in slide-in-from-top-2 duration-300">
                                    <InputLabel htmlFor="day_of_week" value="Day of Week" className="text-gray-700 dark:text-gray-300 font-medium ml-1" />
                                    <select
                                        id="day_of_week"
                                        className="mt-2 block w-full bg-white/50 dark:bg-gray-900/50 backdrop-blur-sm border-gray-200/80 dark:border-gray-700/80 rounded-xl focus:ring-indigo-500/30 focus:border-indigo-500 text-gray-900 dark:text-gray-100 shadow-sm transition-all"
                                        value={data.day_of_week}
                                        onChange={(e) => setData('day_of_week', e.target.value)}
                                    >
                                        <option value="mon">Monday</option>
                                        <option value="tue">Tuesday</option>
                                        <option value="wed">Wednesday</option>
                                        <option value="thu">Thursday</option>
                                        <option value="fri">Friday</option>
                                        <option value="sat">Saturday</option>
                                        <option value="sun">Sunday</option>
                                    </select>
                                    <InputError className="mt-2" message={errors.day_of_week} />
                                </div>
                            )}

                            <div>
                                <InputLabel htmlFor="timezone" value="Timezone" className="text-gray-700 dark:text-gray-300 font-medium ml-1" />
                                <select
                                    id="timezone"
                                    className="mt-2 block w-full bg-white/50 dark:bg-gray-900/50 backdrop-blur-sm border-gray-200/80 dark:border-gray-700/80 rounded-xl focus:ring-indigo-500/30 focus:border-indigo-500 text-gray-900 dark:text-gray-100 shadow-sm transition-all"
                                    value={data.timezone}
                                    onChange={(e) => setData('timezone', e.target.value)}
                                >
                                    {!Intl.supportedValuesOf('timeZone').includes(data.timezone) && (
                                        <option value={data.timezone}>{data.timezone}</option>
                                    )}
                                    {Intl.supportedValuesOf('timeZone').map((tz) => (
                                        <option key={tz} value={tz}>{tz.replace(/_/g, ' ')}</option>
                                    ))}
                                </select>
                                <p className="text-sm text-gray-500 dark:text-gray-400 mt-2 ml-1">The digest will be delivered at {data.scheduled_at} in this timezone.</p>
                                <InputError className="mt-2" message={errors.timezone} />
                            </div>

                            <div>
                                <InputLabel htmlFor="mode" value="Source Mode" className="text-gray-700 dark:text-gray-300 font-medium ml-1" />
                                <select
                                    id="mode"
                                    className="mt-2 block w-full bg-white/50 dark:bg-gray-900/50 backdrop-blur-sm border-gray-200/80 dark:border-gray-700/80 rounded-xl focus:ring-indigo-500/30 focus:border-indigo-500 text-gray-900 dark:text-gray-100 shadow-sm transition-all"
                                    value={data.mode}
                                    onChange={(e) => setData('mode', e.target.value)}
                                >
                                    <option value="channels">Specific Channels</option>
                                    <option value="search_term">Search Term (All Channels)</option>
                                    <option value="mixed">Mixed (Channels + Search)</option>
                                </select>
                                <InputError className="mt-2" message={errors.mode} />
                            </div>

                            {data.mode !== 'channels' && (
                                <div className="animate-in fade-in slide-in-from-top-2 duration-300">
                                    <InputLabel htmlFor="search_term" value="Search Term" className="text-gray-700 dark:text-gray-300 font-medium ml-1" />
                                    <TextInput
                                        id="search_term"
                                        className="mt-2 block w-full bg-white/50 dark:bg-gray-900/50 backdrop-blur-sm border-gray-200/80 dark:border-gray-700/80 rounded-xl focus:ring-indigo-500/30 focus:border-indigo-500 shadow-sm transition-all"
                                        value={data.search_term}
                                        onChange={(e) => setData('search_term', e.target.value)}
                                        placeholder="e.g., AI, Python, Crypto"
                                    />
                                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-2 ml-1">Videos matching this term will be included.</p>
                                    <InputError className="mt-2" message={errors.search_term} />
                                </div>
                            )}

                            {/* Video Content Types */}
                            <div>
                                <InputLabel value="Content Types" className="text-gray-700 dark:text-gray-300 font-medium ml-1" />
                                <p className="text-sm text-gray-500 dark:text-gray-400 mt-1 ml-1 mb-3">Choose which types of content to fetch from channels.</p>
                                <div className="flex flex-wrap gap-3">
                                    {([
                                        { value: 'videos', label: 'Videos', icon: '🎬' },
                                        { value: 'shorts', label: 'Shorts', icon: '⚡' },
                                        { value: 'streams', label: 'Live Streams', icon: '🔴' },
                                    ] as const).map(({ value, label, icon }) => {
                                        const checked = data.video_types.includes(value);
                                        return (
                                            <label
                                                key={value}
                                                className={`flex items-center gap-2 px-4 py-2.5 rounded-xl border-2 cursor-pointer transition-all select-none ${
                                                    checked
                                                    ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300'
                                                    : 'border-gray-200 dark:border-gray-700 bg-white/50 dark:bg-gray-900/40 text-gray-500 dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-600'
                                                }`}
                                            >
                                                <input
                                                    type="checkbox"
                                                    className="sr-only"
                                                    checked={checked}
                                                    onChange={() => {
                                                        const current = new Set(data.video_types);
                                                        if (current.has(value)) {
                                                            current.delete(value);
                                                        } else {
                                                            current.add(value);
                                                        }
                                                        setData('video_types', Array.from(current));
                                                    }}
                                                />
                                                <span className="text-base">{icon}</span>
                                                <span className="text-sm font-semibold">{label}</span>
                                                {checked && (
                                                    <svg className="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 13l4 4L19 7" />
                                                    </svg>
                                                )}
                                            </label>
                                        );
                                    })}
                                </div>
                                {data.video_types.length === 0 && (
                                    <p className="text-xs text-amber-600 dark:text-amber-400 mt-2 ml-1">⚠️ Select at least one content type.</p>
                                )}
                            </div>

                            {data.mode !== 'search_term' && (
                                <div className="animate-in fade-in slide-in-from-top-2 duration-300">
                                    <InputLabel value="Select Channels" className="text-gray-700 dark:text-gray-300 font-medium ml-1" />
                                    <div className="mt-2 max-h-60 overflow-y-auto bg-white/40 dark:bg-gray-900/40 backdrop-blur-md border border-gray-200/80 dark:border-gray-700/80 rounded-xl p-3 shadow-inner">
                                        {availableChannels.length === 0 ? (
                                            <div className="p-4 text-center">
                                                <p className="text-sm text-gray-500 dark:text-gray-400">No channels found. Add channels first.</p>
                                            </div>
                                        ) : (
                                            <div className="space-y-1">
                                                {availableChannels.map((channel) => (
                                                    <label key={channel.id} className="flex items-center space-x-3 p-2.5 hover:bg-gray-100/50 dark:hover:bg-gray-800/60 rounded-lg cursor-pointer transition-colors border border-transparent hover:border-gray-200/50 dark:hover:border-gray-700/50">
                                                        <Checkbox
                                                            checked={data.channel_ids.includes(channel.id)}
                                                            onChange={() => toggleChannel(channel.id)}
                                                            className="rounded-md border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500/30"
                                                        />
                                                        <div className="flex items-center flex-1 min-w-0">
                                                            <div className="relative w-7 h-7 mr-3 flex-shrink-0">
                                                                <img src={channel.thumbnail_url} alt="" className={`h-full w-full rounded-full object-cover shadow-sm bg-gray-100 dark:bg-gray-800 ${channel.is_paused ? 'grayscale opacity-60' : ''}`} />
                                                            </div>
                                                            <span className={`text-sm font-medium ${channel.is_paused ? 'text-gray-400 dark:text-gray-500' : 'text-gray-900 dark:text-gray-200'}`}>{channel.name}</span>
                                                            {channel.is_paused && (
                                                                <span className="ml-2 flex-shrink-0 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                                                                    Paused
                                                                </span>
                                                            )}
                                                        </div>
                                                    </label>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                    <InputError className="mt-2" message={errors.channel_ids} />
                                </div>
                            )}

                            <div className="pt-4 border-t border-gray-100 dark:border-gray-700/50">
                                <InputLabel htmlFor="custom_prompt" value="Individual Video Summary Prompt (Optional)" className="text-gray-700 dark:text-gray-300 font-medium ml-1" />
                                <textarea
                                    id="custom_prompt"
                                    className="mt-2 block w-full bg-white/50 dark:bg-gray-900/50 backdrop-blur-sm border-gray-200/80 dark:border-gray-700/80 rounded-xl focus:ring-indigo-500/30 focus:border-indigo-500 text-gray-900 dark:text-gray-100 shadow-sm transition-all resize-y"
                                    rows={3}
                                    value={data.custom_prompt}
                                    onChange={(e) => setData('custom_prompt', e.target.value)}
                                    placeholder="e.g., Focus on coding tutorials and ignore vlogs."
                                />
                                <p className="text-sm text-gray-500 dark:text-gray-400 mt-2 ml-1">Instructions for the AI when summarizing EACH INDIVIDUAL video in this digest.</p>
                                <InputError className="mt-2" message={errors.custom_prompt} />
                            </div>

                            <div className="pt-2">
                                <InputLabel htmlFor="global_summary_prompt" value="Global Digest Summary Prompt (Optional)" className="text-gray-700 dark:text-gray-300 font-medium ml-1" />
                                <textarea
                                    id="global_summary_prompt"
                                    className="mt-2 block w-full bg-white/50 dark:bg-gray-900/50 backdrop-blur-sm border-gray-200/80 dark:border-gray-700/80 rounded-xl focus:ring-indigo-500/30 focus:border-indigo-500 text-gray-900 dark:text-gray-100 shadow-sm transition-all resize-y"
                                    rows={3}
                                    value={data.global_summary_prompt}
                                    onChange={(e) => setData('global_summary_prompt', e.target.value)}
                                    placeholder="e.g., Write a comprehensive executive summary of all the videos combined."
                                />
                                <p className="text-sm text-gray-500 dark:text-gray-400 mt-2 ml-1">Instructions for the AI when creating the grand summary of ALL transcripts obtained from this digest.</p>
                                <InputError className="mt-2" message={errors.global_summary_prompt} />
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-6 border-t border-gray-100 dark:border-gray-700/50 mt-8">
                                <Link
                                    href={route('digests.index')}
                                    className="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-medium border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 shadow-sm transition-all hover:shadow-md active:scale-95"
                                >
                                    Cancel
                                </Link>
                                <PrimaryButton className="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium shadow-sm transition-all hover:shadow-md active:scale-95 border border-transparent" disabled={processing}>
                                    {processing ? (
                                        <>
                                            <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" viewBox="0 0 24 24" fill="none">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Creating...
                                        </>
                                    ) : (
                                        'Create Digest'
                                    )}
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
