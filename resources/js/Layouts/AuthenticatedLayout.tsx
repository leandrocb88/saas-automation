import ApplicationLogo from '@/Components/ApplicationLogo';
import Footer from '@/Components/Footer';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import ThemeToggle from '@/Components/ThemeToggle';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const user = usePage().props.auth.user as User | null;
    // @ts-ignore
    const guest = usePage().props.auth.guest;
    const { quota } = user || {};

    interface User {
        name: string;
        email: string;
        is_admin: boolean;
        quota?: {
            used: number;
            limit: number;
            remaining: number;
            plan: string;
            plan_name: string;
            next_reset_at?: string;
        };
    }

    interface Settings {
        sign_up_enabled: boolean;
        admin_only_access: boolean;
    }

    const { settings } = usePage().props as unknown as { settings: Settings };

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);

    // Generate user initials for avatar
    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map((n) => n[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

    // Credit pill color based on remaining percentage
    const getCreditColor = () => {
        if (!quota) return 'bg-gray-100 text-gray-600';
        const pct = (quota.remaining / quota.limit) * 100;
        if (pct > 50) return 'bg-emerald-50 text-emerald-700 ring-emerald-200/80 dark:bg-emerald-900/20 dark:text-emerald-400 dark:ring-emerald-800';
        if (pct > 20) return 'bg-amber-50 text-amber-700 ring-amber-200/80 dark:bg-amber-900/20 dark:text-amber-400 dark:ring-amber-800';
        return 'bg-red-50 text-red-700 ring-red-200/80 dark:bg-red-900/20 dark:text-red-400 dark:ring-red-800';
    };

    const getCreditDot = () => {
        if (!quota) return 'bg-gray-400';
        const pct = (quota.remaining / quota.limit) * 100;
        if (pct > 50) return 'bg-emerald-500';
        if (pct > 20) return 'bg-amber-500';
        return 'bg-red-500';
    };

    return (
        <div className="min-h-screen bg-gray-100 dark:bg-gray-900">
            <nav
                className={`sticky top-0 z-50 border-b transition-colors duration-200 ${showingNavigationDropdown
                    ? 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700'
                    : 'bg-white/80 backdrop-blur-xl border-gray-200/60 dark:bg-gray-800/80 dark:border-gray-700/60'
                    }`}
            >
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 justify-between items-center">
                        <div className="flex items-center">
                            <div className="flex shrink-0 items-center">
                                <Link href="/" className="transition-transform hover:scale-105 duration-200">
                                    <ApplicationLogo className="block h-9 w-auto fill-current text-indigo-600 dark:text-indigo-400" />
                                </Link>
                            </div>

                            <div className="hidden space-x-1 sm:-my-px sm:ms-8 sm:flex">
                                {route().has('youtube.history') && (
                                    <NavLink
                                        href={route('youtube.history')}
                                        active={route().current('youtube.history') || route().current('youtube.show')}
                                    >
                                        History
                                    </NavLink>
                                )}
                                <NavLink
                                    href={route('youtube.channel')}
                                    active={route().current('youtube.channel')}
                                >
                                    Channels
                                </NavLink>
                                <NavLink
                                    href={route('youtube.subscriptions')}
                                    active={route().current('youtube.subscriptions')}
                                >
                                    Subscriptions
                                </NavLink>
                                <NavLink
                                    href={route('youtube.digest')}
                                    active={route().current('youtube.digest')}
                                >
                                    Digest
                                </NavLink>
                                <NavLink
                                    href={route('plans')}
                                    active={route().current('plans')}
                                >
                                    Plans
                                </NavLink>
                                {user && user.is_admin && (
                                    <NavLink
                                        href={route('admin.dashboard')}
                                        active={route().current('admin.dashboard')}
                                    >
                                        Admin
                                    </NavLink>
                                )}
                            </div>
                        </div>

                        <div className="hidden sm:ms-6 sm:flex sm:items-center">
                            <div className="relative ms-3 flex items-center gap-3">
                                <ThemeToggle />
                                {/* User Quota — color-coded credit pill */}
                                {user && quota && (
                                    <Link
                                        href={route('plans')}
                                        className={`group inline-flex items-center gap-2 px-3 py-1.5 rounded-full ring-1 ring-inset text-xs font-semibold transition-all hover:shadow-sm ${getCreditColor()}`}
                                    >
                                        <span className={`inline-block h-1.5 w-1.5 rounded-full ${getCreditDot()}`} />
                                        {quota.remaining}
                                        <span className="font-normal opacity-70">credits</span>
                                    </Link>
                                )}

                                {/* Guest Quota */}
                                {!user && guest && guest.quota && (
                                    <Link
                                        href={route('register')}
                                        className="group inline-flex items-center gap-2 bg-indigo-50 dark:bg-indigo-900/20 px-3 py-1.5 rounded-full ring-1 ring-inset ring-indigo-200 dark:ring-indigo-800 text-xs font-bold text-indigo-700 dark:text-indigo-300 hover:ring-indigo-300 transition-all hover:shadow-sm"
                                    >
                                        <span className="inline-block h-1.5 w-1.5 rounded-full bg-indigo-500" />
                                        {guest.quota.remaining} Free Credits
                                    </Link>
                                )}

                                {user ? (
                                    <Dropdown>
                                        <Dropdown.Trigger>
                                            <button
                                                type="button"
                                                className="group flex items-center gap-2.5 rounded-full py-1.5 pl-1.5 pr-3 transition-all duration-200 hover:bg-gray-100 dark:hover:bg-gray-700/50 focus:outline-none"
                                            >
                                                {/* Avatar with initials */}
                                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 text-xs font-bold text-white shadow-sm ring-2 ring-white dark:ring-gray-800">
                                                    {getInitials(user.name)}
                                                </div>
                                                <span className="hidden text-sm font-medium text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white lg:block">
                                                    {user.name}
                                                </span>
                                                <svg className="h-4 w-4 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-300 transition-colors" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
                                                </svg>
                                            </button>
                                        </Dropdown.Trigger>

                                        <Dropdown.Content>
                                            {/* User info header in dropdown */}
                                            <div className="px-4 py-3 border-b border-gray-100 dark:border-gray-600">
                                                <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    {user.name}
                                                </p>
                                                <p className="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                    {user.email}
                                                </p>
                                                {quota && (
                                                    <div className="mt-2 flex items-center gap-2">
                                                        <div className="flex-1 h-1.5 rounded-full bg-gray-200 dark:bg-gray-600 overflow-hidden">
                                                            <div
                                                                className={`h-full rounded-full transition-all ${(quota.remaining / quota.limit) * 100 > 50
                                                                    ? 'bg-emerald-500'
                                                                    : (quota.remaining / quota.limit) * 100 > 20
                                                                        ? 'bg-amber-500'
                                                                        : 'bg-red-500'
                                                                    }`}
                                                                style={{ width: `${Math.min(100, (quota.remaining / quota.limit) * 100)}%` }}
                                                            />
                                                        </div>
                                                        <span className="text-[10px] font-semibold text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                                            {quota.remaining}/{quota.limit}
                                                        </span>
                                                    </div>
                                                )}
                                            </div>
                                            <Dropdown.Link href={route('profile.edit')}>
                                                Profile
                                            </Dropdown.Link>
                                            <Dropdown.Link href={route('billing')}>
                                                Billing
                                            </Dropdown.Link>
                                            <Dropdown.Link href={route('logout')} method="post" as="button">
                                                Log Out
                                            </Dropdown.Link>
                                        </Dropdown.Content>
                                    </Dropdown>
                                ) : (
                                    <div className="flex items-center gap-3">
                                        <Link
                                            href={route('login')}
                                            className="text-sm font-semibold text-gray-700 hover:text-indigo-600 dark:text-gray-300 dark:hover:text-indigo-400 transition-colors"
                                        >
                                            Log in
                                        </Link>
                                        {settings?.sign_up_enabled !== false && settings?.admin_only_access !== true && (
                                            <Link
                                                href={route('register')}
                                                className="rounded-full bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-all"
                                            >
                                                Get Started
                                            </Link>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="-me-2 flex items-center sm:hidden">
                            {/* Mobile credits */}
                            {user && quota && (
                                <Link
                                    href={route('plans')}
                                    className={`mr-2 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full ring-1 ring-inset text-[11px] font-semibold ${getCreditColor()}`}
                                >
                                    <span className={`inline-block h-1.5 w-1.5 rounded-full ${getCreditDot()}`} />
                                    {quota.remaining}
                                </Link>
                            )}
                            <button
                                onClick={() => setShowingNavigationDropdown((previousState) => !previousState)}
                                className="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none dark:text-gray-500 dark:hover:bg-gray-900 dark:hover:text-gray-400 dark:focus:bg-gray-900 dark:focus:text-gray-400"
                            >
                                <svg className="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        className={!showingNavigationDropdown ? 'inline-flex' : 'hidden'}
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        className={showingNavigationDropdown ? 'inline-flex' : 'hidden'}
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                {/* Mobile menu — solid bg when open */}
                <div
                    className={
                        (showingNavigationDropdown ? 'block' : 'hidden') +
                        ' sm:hidden bg-white dark:bg-gray-800'
                    }
                >
                    <div className="space-y-1 pb-3 pt-2">

                        {route().has('youtube.history') && (
                            <ResponsiveNavLink
                                href={route('youtube.history')}
                                active={route().current('youtube.history') || route().current('youtube.show')}
                            >
                                History
                            </ResponsiveNavLink>
                        )}
                        <ResponsiveNavLink
                            href={route('youtube.channel')}
                            active={route().current('youtube.channel')}
                        >
                            Channels
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            href={route('youtube.subscriptions')}
                            active={route().current('youtube.subscriptions')}
                        >
                            Subscriptions
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            href={route('youtube.digest')}
                            active={route().current('youtube.digest')}
                        >
                            Digest
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            href={route('plans')}
                            active={route().current('plans')}
                        >
                            Plans
                        </ResponsiveNavLink>
                        {user && user.is_admin && (
                            <ResponsiveNavLink
                                href={route('admin.dashboard')}
                                active={route().current('admin.dashboard')}
                            >
                                Admin
                            </ResponsiveNavLink>
                        )}
                    </div>

                    <div className="border-t border-gray-200 pb-1 pt-4 dark:border-gray-600">
                        {user ? (
                            <div className="flex items-center justify-between px-4">
                                <div className="flex items-center gap-3">
                                    {/* Mobile avatar */}
                                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 text-sm font-bold text-white shadow-sm">
                                        {getInitials(user.name)}
                                    </div>
                                    <div>
                                        <div className="text-base font-medium text-gray-800 dark:text-gray-200">
                                            {user.name}
                                        </div>
                                        <div className="text-sm font-medium text-gray-500">
                                            {user.email}
                                        </div>
                                    </div>
                                </div>
                                <ThemeToggle />
                            </div>
                        ) : (
                            <div className="px-4 space-y-2">
                                <Link
                                    href={route('login')}
                                    className="block text-base font-medium text-gray-800 dark:text-gray-200"
                                >
                                    Log in
                                </Link>
                                {settings?.sign_up_enabled !== false && settings?.admin_only_access !== true && (
                                    <Link
                                        href={route('register')}
                                        className="block text-base font-medium text-gray-800 dark:text-gray-200"
                                    >
                                        Register
                                    </Link>
                                )}
                            </div>
                        )}

                        {user && (
                            <div className="mt-3 space-y-1">
                                <ResponsiveNavLink href={route('profile.edit')}>
                                    Profile
                                </ResponsiveNavLink>
                                <ResponsiveNavLink href={route('billing')}>
                                    Billing
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    method="post"
                                    href={route('logout')}
                                    as="button"
                                >
                                    Log Out
                                </ResponsiveNavLink>
                            </div>
                        )}
                    </div>
                </div>
            </nav>

            {header && (
                <header className="bg-white shadow dark:bg-gray-800">
                    <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}
            <main>{children}</main>
            <Footer />
        </div>
    );
}
