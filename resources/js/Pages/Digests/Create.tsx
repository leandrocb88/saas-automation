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
        <AuthenticatedLayout
            // @ts-ignore
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Create New Digest</h2>}
        >
            <Head title="Create Digest" />

            <div className="py-12">
                <div className="max-w-2xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900 dark:text-gray-100">
                            <form onSubmit={submit} className="space-y-6">

                                <div>
                                    <InputLabel htmlFor="name" value="Digest Name" />
                                    <TextInput
                                        id="name"
                                        className="mt-1 block w-full"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        required
                                        isFocused
                                        placeholder="e.g., Morning Tech News"
                                    />
                                    <InputError className="mt-2" message={errors.name} />
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <InputLabel htmlFor="frequency" value="Frequency" />
                                        <select
                                            id="frequency"
                                            className="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                            value={data.frequency}
                                            onChange={(e) => setData('frequency', e.target.value)}
                                        >
                                            <option value="daily">Daily</option>
                                            <option value="weekly">Weekly</option>
                                        </select>
                                        <InputError className="mt-2" message={errors.frequency} />
                                    </div>

                                    <div>
                                        <InputLabel htmlFor="scheduled_at" value="Time" />
                                        <TextInput
                                            id="scheduled_at"
                                            type="time"
                                            className="mt-1 block w-full"
                                            value={data.scheduled_at}
                                            onChange={(e) => setData('scheduled_at', e.target.value)}
                                            required
                                        />
                                        <InputError className="mt-2" message={errors.scheduled_at} />
                                    </div>
                                </div>

                                {data.frequency === 'weekly' && (
                                    <div>
                                        <InputLabel htmlFor="day_of_week" value="Day of Week" />
                                        <select
                                            id="day_of_week"
                                            className="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
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
                                    <InputLabel htmlFor="mode" value="Source Mode" />
                                    <select
                                        id="mode"
                                        className="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
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
                                    <div>
                                        <InputLabel htmlFor="search_term" value="Search Term" />
                                        <TextInput
                                            id="search_term"
                                            className="mt-1 block w-full"
                                            value={data.search_term}
                                            onChange={(e) => setData('search_term', e.target.value)}
                                            placeholder="e.g., AI, Python, Crypto"
                                        />
                                        <p className="text-sm text-gray-500 mt-1">Videos matching this term will be included.</p>
                                        <InputError className="mt-2" message={errors.search_term} />
                                    </div>
                                )}

                                {data.mode !== 'search_term' && (
                                    <div>
                                        <InputLabel value="Select Channels" />
                                        <div className="mt-2 max-h-60 overflow-y-auto border border-gray-300 dark:border-gray-700 rounded-md p-2">
                                            {availableChannels.length === 0 ? (
                                                <p className="text-sm text-gray-500">No channels found. Add channels first.</p>
                                            ) : (
                                                availableChannels.map((channel) => (
                                                    <label key={channel.id} className="flex items-center space-x-3 p-2 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer">
                                                        <Checkbox
                                                            checked={data.channel_ids.includes(channel.id)}
                                                            onChange={() => toggleChannel(channel.id)}
                                                        />
                                                        <div className="flex items-center">
                                                            <img src={channel.thumbnail_url} alt="" className="h-6 w-6 rounded-full mr-2" />
                                                            <span className="text-gray-900 dark:text-gray-200 text-sm">{channel.name}</span>
                                                        </div>
                                                    </label>
                                                ))
                                            )}
                                        </div>
                                        <InputError className="mt-2" message={errors.channel_ids} />
                                    </div>
                                )}

                                <div>
                                    <InputLabel htmlFor="custom_prompt" value="Custom AI Prompt (Optional)" />
                                    <textarea
                                        id="custom_prompt"
                                        className="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                        rows={3}
                                        value={data.custom_prompt}
                                        onChange={(e) => setData('custom_prompt', e.target.value)}
                                        placeholder="e.g., Focus on coding tutorials and ignore vlogs."
                                    />
                                    <p className="text-sm text-gray-500 mt-1">Instructions for the AI when summarizing videos for this digest.</p>
                                    <InputError className="mt-2" message={errors.custom_prompt} />
                                </div>

                                <div className="flex items-center justify-end">
                                    <Link
                                        href={route('digests.index')}
                                        className="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800 mr-4"
                                    >
                                        Cancel
                                    </Link>
                                    <PrimaryButton className="ml-4" disabled={processing}>
                                        Create Digest
                                    </PrimaryButton>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
