import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState, FormEventHandler } from 'react';
import Modal from '@/Components/Modal';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import DangerButton from '@/Components/DangerButton';
import Toggle from '@/Components/Toggle';

interface User {
    id: number;
    name: string;
    email: string;
    is_blocked: boolean;
    is_admin: boolean;
    channels_count: number;
    created_at: string;
}

interface PaginatedUsers {
    data: User[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Stats {
    service: string;
    totalUsers: number;
    totalCreditsConsumed: number;
    totalVideosProcessed: number;
    guestAccess: boolean;
    signUpEnabled: boolean;
    adminOnly: boolean;
}

export default function AdminDashboard({ auth, users, stats }: { auth: any, users: PaginatedUsers, stats: Stats }) {
    const [isAddingUser, setIsAddingUser] = useState(false);
    const [editingUser, setEditingUser] = useState<User | null>(null);
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState<number | null>(null);

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        name: '',
        email: '',
        password: '',
        is_admin: false,
    });

    const handleEditUser = (user: User) => {
        setEditingUser(user);
        setData({
            name: user.name,
            email: user.email,
            password: '',
            is_admin: (user as any).is_admin || false, // Assuming is_admin comes from backend, though type def above didn't have it. It should.
        });
        setIsAddingUser(true); // Reusing the modal for simplicity, or we can make a separate one. Let's make separate to be clean or just adapt the title.
        // Actually adhering to the plan: "Add Edit User modal" implies a separate one or adapted one.
        // Let's use a separate state `isEditingMode`? 
    };

    const handleToggleBlock = (userId: number) => {
        router.post(route('admin.users.block', userId));
    };

    const handleDeleteUser = (userId: number) => {
        router.delete(route('admin.users.destroy', userId), {
            onSuccess: () => setConfirmingUserDeletion(null),
        });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (editingUser) {
            put(route('admin.users.update', editingUser.id), {
                onSuccess: () => {
                    closeModal();
                },
            });
        } else {
            post(route('admin.users.store'), {
                onSuccess: () => {
                    closeModal();
                },
            });
        }
    };

    const closeModal = () => {
        setIsAddingUser(false);
        setEditingUser(null);
        reset();
        clearErrors();
    };

    return (
        <AuthenticatedLayout>
            <Head title={`${stats.service} Admin Dashboard`} />

            {/* Hero */}
            <div className="relative overflow-hidden">
                <div className="absolute inset-0 bg-gradient-to-br from-gray-100 via-slate-100 to-gray-200 dark:from-gray-900 dark:via-slate-900 dark:to-gray-900" />
                <div className="absolute top-10 right-20 w-64 h-64 bg-indigo-500/5 dark:bg-indigo-500/10 rounded-full blur-3xl" />
                <div className="absolute bottom-0 left-10 w-80 h-80 bg-purple-500/5 dark:bg-purple-500/10 rounded-full blur-3xl" />

                <div className="relative py-10 lg:py-14">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="flex justify-between items-center">
                            <div>
                                <div className="inline-flex items-center rounded-full border border-red-500/30 bg-red-500/10 backdrop-blur-sm px-3 py-1 text-xs font-medium text-red-600 dark:text-red-300 mb-3">
                                    <svg className="w-3 h-3 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                    {stats.service} Admin Only
                                </div>
                                <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">{stats.service} Admin Dashboard</h1>
                                <div className="flex flex-wrap items-center gap-3">
                                    {/* Guest Mode */}
                                    <div className="flex items-center gap-3 bg-white/50 dark:bg-white/5 backdrop-blur-sm px-4 py-2 rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
                                        <div className="flex flex-col">
                                            <span className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Guest Access</span>
                                            <span className={`text-sm font-bold ${stats.guestAccess ? 'text-green-600 dark:text-green-400' : 'text-gray-600 dark:text-gray-400'}`}>
                                                {stats.guestAccess ? 'Enabled' : 'Disabled'}
                                            </span>
                                        </div>
                                        <Toggle
                                            checked={stats.guestAccess}
                                            onChange={() => router.post(route('admin.settings.guest_access'))}
                                        />
                                    </div>

                                    {/* Sign Ups */}
                                    <div className="flex items-center gap-3 bg-white/50 dark:bg-white/5 backdrop-blur-sm px-4 py-2 rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
                                        <div className="flex flex-col">
                                            <span className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sign-ups</span>
                                            <span className={`text-sm font-bold ${stats.signUpEnabled ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400'}`}>
                                                {stats.signUpEnabled ? 'Open' : 'Closed'}
                                            </span>
                                        </div>
                                        <Toggle
                                            checked={stats.signUpEnabled}
                                            onChange={() => router.post(route('admin.settings.sign_up'))}
                                        />
                                    </div>

                                    {/* Admin Only */}
                                    <div className="flex items-center gap-3 bg-white/50 dark:bg-white/5 backdrop-blur-sm px-4 py-2 rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
                                        <div className="flex flex-col">
                                            <span className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Maintenance</span>
                                            <span className={`text-sm font-bold ${stats.adminOnly ? 'text-red-500 dark:text-red-400' : 'text-green-600 dark:text-green-400'}`}>
                                                {stats.adminOnly ? 'Active' : 'Inactive'}
                                            </span>
                                        </div>
                                        <Toggle
                                            checked={stats.adminOnly}
                                            onChange={() => router.post(route('admin.settings.admin_only'))}
                                        />
                                    </div>
                                </div>
                            </div>

                            <button
                                onClick={() => setIsAddingUser(true)}
                                className="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold rounded-lg transition-colors shadow-lg shadow-indigo-500/20"
                            >
                                <svg className="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                </svg>
                                Add New User
                            </button>
                        </div>

                        {/* Stats Grid */}
                        <div className="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div className="bg-white/50 dark:bg-white/5 backdrop-blur-sm rounded-xl p-5 ring-1 ring-gray-200 dark:ring-white/10">
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 rounded-lg bg-indigo-500/10 dark:bg-indigo-500/20 flex items-center justify-center">
                                        <svg className="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p className="text-2xl font-bold text-gray-900 dark:text-white">{stats.totalUsers}</p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">Total Users</p>
                                    </div>
                                </div>
                            </div>
                            <div className="bg-white/50 dark:bg-white/5 backdrop-blur-sm rounded-xl p-5 ring-1 ring-gray-200 dark:ring-white/10">
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 rounded-lg bg-purple-500/10 dark:bg-purple-500/20 flex items-center justify-center">
                                        <svg className="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p className="text-2xl font-bold text-gray-900 dark:text-white">{stats.totalCreditsConsumed}</p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">Credits Consumed</p>
                                    </div>
                                </div>
                            </div>
                            <div className="bg-white/50 dark:bg-white/5 backdrop-blur-sm rounded-xl p-5 ring-1 ring-gray-200 dark:ring-white/10">
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 rounded-lg bg-red-500/10 dark:bg-red-500/20 flex items-center justify-center">
                                        <svg className="w-5 h-5 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p className="text-2xl font-bold text-gray-900 dark:text-white">{stats.totalVideosProcessed}</p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">Videos Processed</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Users Table */}
            <div className="py-10 bg-gray-50 dark:bg-gray-900">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="bg-white dark:bg-gray-800 rounded-2xl ring-1 ring-gray-200/50 dark:ring-gray-700/50 shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                            <h3 className="text-base font-semibold text-gray-900 dark:text-white">Users</h3>
                            <span className="text-xs text-gray-500 bg-gray-100 dark:bg-gray-700 px-2.5 py-1 rounded-full">{users.total} total</span>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50/80 dark:bg-gray-700/50">
                                    <tr>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Role</th>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Channels</th>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Usage</th>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Joined</th>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                        <th scope="col" className="relative px-6 py-3"><span className="sr-only">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                    {users.data.map((user) => (
                                        <tr key={user.id} className="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors">
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-xs font-bold shadow-sm">
                                                        {user.name.charAt(0).toUpperCase()}
                                                    </div>
                                                    <div>
                                                        <p className="text-sm font-medium text-gray-900 dark:text-white">{user.name}</p>
                                                        <p className="text-xs text-gray-500">{user.email}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${user.is_admin ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'}`}>
                                                    {user.is_admin ? 'Admin' : 'User'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 font-medium">
                                                {user.channels_count}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                                    {(user as any).daily_usage || 0} credits
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {new Date(user.created_at).toLocaleDateString()}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className={`inline-flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full ${user.is_blocked ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'}`}>
                                                    <span className={`w-1.5 h-1.5 rounded-full ${user.is_blocked ? 'bg-red-500' : 'bg-green-500'}`} />
                                                    {user.is_blocked ? 'Blocked' : 'Active'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <div className="flex items-center justify-end gap-2">
                                                    {user.id !== auth.user.id && (
                                                        <button
                                                            onClick={() => handleToggleBlock(user.id)}
                                                            className={`text-xs font-medium px-3 py-1.5 rounded-lg transition-all ${user.is_blocked ? 'text-green-700 bg-green-50 hover:bg-green-100 dark:text-green-300 dark:bg-green-900/20 dark:hover:bg-green-900/30 ring-1 ring-green-200 dark:ring-green-800' : 'text-red-700 bg-red-50 hover:bg-red-100 dark:text-red-300 dark:bg-red-900/20 dark:hover:bg-red-900/30 ring-1 ring-red-200 dark:ring-red-800'}`}
                                                        >
                                                            {user.is_blocked ? 'Unblock' : 'Block'}
                                                        </button>
                                                    )}

                                                    <button
                                                        onClick={() => {
                                                            setEditingUser(user);
                                                            setData({
                                                                name: user.name,
                                                                email: user.email,
                                                                password: '',
                                                                is_admin: (user as any).is_admin || false,
                                                            });
                                                            setIsAddingUser(true);
                                                        }}
                                                        className="text-xs font-medium px-3 py-1.5 rounded-lg text-gray-700 bg-gray-100 hover:bg-gray-200 dark:text-gray-300 dark:bg-gray-800 dark:hover:bg-gray-700 ring-1 ring-gray-200 dark:ring-gray-700 transition-all"
                                                    >
                                                        Edit
                                                    </button>

                                                    <button
                                                        onClick={() => router.post(route('admin.users.reset_credits', user.id))}
                                                        className="text-xs font-medium px-3 py-1.5 rounded-lg text-indigo-700 bg-indigo-50 hover:bg-indigo-100 dark:text-indigo-300 dark:bg-indigo-900/20 dark:hover:bg-indigo-900/30 ring-1 ring-indigo-200 dark:ring-indigo-800 transition-all"
                                                        title="Reset Credits to 0"
                                                    >
                                                        Reset Credits
                                                    </button>

                                                    {user.id !== auth.user.id ? (
                                                        <button
                                                            onClick={() => setConfirmingUserDeletion(user.id)}
                                                            className="text-gray-400 hover:text-red-500 transition-colors p-1"
                                                            title="Delete User"
                                                        >
                                                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        </button>
                                                    ) : (
                                                        <span className="text-xs text-gray-400 italic ml-2">You</span>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {users.last_page > 1 && (
                            <div className="px-6 py-4 border-t border-gray-100 dark:border-gray-700 flex justify-center gap-1">
                                {users.links.map((link, i) => (
                                    <Link
                                        key={i}
                                        href={link.url || '#'}
                                        className={`px-3 py-1.5 text-xs font-medium rounded-lg transition-all ${link.active ? 'bg-indigo-600 text-white shadow-sm' : link.url ? 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700' : 'text-gray-300 dark:text-gray-600 cursor-not-allowed'}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        preserveScroll
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Add User Modal */}
            <Modal show={isAddingUser} onClose={closeModal}>
                <form onSubmit={submit} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        {editingUser ? 'Edit' : 'Add New'} {stats.service} User
                    </h2>

                    <div className="mt-6">
                        <InputLabel htmlFor="name" value="Name" />
                        <TextInput
                            id="name"
                            type="text"
                            name="name"
                            value={data.name}
                            className="mt-1 block w-full"
                            isFocused
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                        <InputError message={errors.name} className="mt-2" />
                    </div>

                    <div className="mt-4">
                        <InputLabel htmlFor="email" value="Email" />
                        <TextInput
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            className="mt-1 block w-full"
                            onChange={(e) => setData('email', e.target.value)}
                            required
                        />
                        <InputError message={errors.email} className="mt-2" />
                    </div>

                    <div className="mt-4">
                        <InputLabel htmlFor="password" value="Password" />
                        <TextInput
                            id="password"
                            type="password"
                            name="password"
                            value={data.password}
                            className="mt-1 block w-full"
                            onChange={(e) => setData('password', e.target.value)}
                            required={!editingUser}
                        />
                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    <div className="mt-4 flex items-center">
                        <label className="flex items-center">
                            <input
                                type="checkbox"
                                name="is_admin"
                                checked={data.is_admin}
                                onChange={(e) => setData('is_admin', e.target.checked)}
                                className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                            />
                            <span className="ms-2 text-sm text-gray-600 dark:text-gray-400">Is Admin</span>
                        </label>
                    </div>

                    <div className="mt-6 flex justify-end">
                        <SecondaryButton onClick={closeModal}>Cancel</SecondaryButton>
                        <PrimaryButton className="ms-3" disabled={processing}>
                            {editingUser ? 'Update User' : 'Create User'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Delete Confirmation Modal */}
            <Modal show={!!confirmingUserDeletion} onClose={() => setConfirmingUserDeletion(null)}>
                <div className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Are you sure you want to delete this user?
                    </h2>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Once this account is deleted, all of its resources and data will be permanently deleted.
                    </p>
                    <div className="mt-6 flex justify-end">
                        <SecondaryButton onClick={() => setConfirmingUserDeletion(null)}>Cancel</SecondaryButton>
                        <DangerButton
                            className="ms-3"
                            onClick={() => confirmingUserDeletion && handleDeleteUser(confirmingUserDeletion)}
                        >
                            Delete Account
                        </DangerButton>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
