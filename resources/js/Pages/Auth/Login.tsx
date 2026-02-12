import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Log in" />

            <div className="mb-6 text-center">
                <h3 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Welcome back</h3>
                <p className="text-sm text-gray-500 dark:text-gray-400 mt-2">Log in to your account to continue.</p>
            </div>

            {status && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <div className="mb-6">
                <a
                    href={route('auth.google')}
                    className="flex w-full items-center justify-center gap-3 rounded-xl bg-white dark:bg-gray-800 px-4 py-3 text-sm font-semibold text-gray-700 dark:text-gray-200 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                >
                    <svg className="h-5 w-5" aria-hidden="true" viewBox="0 0 24 24">
                        <path d="M12.0003 20.45c4.6667 0 8.45-3.7833 8.45-8.45 0-.5833-.05-1.15-.15-1.7H12.0003v3.2h4.8667c-.2167 1.1333-1.2834 3.2-4.8667 3.2-2.9167 0-5.2834-2.3667-5.2834-5.2833 0-2.9167 2.3667-5.2833 5.2834-5.2833 1.5833 0 2.9833.5667 4.1 1.6333l2.3666-2.3666C16.9336 3.9833 14.6503 3.05 12.0003 3.05 7.3336 3.05 3.5503 6.8333 3.5503 11.5c0 4.6667 3.7833 8.45 8.45 8.45z" fill="#EA4335" />
                    </svg>
                    <span className="text-sm font-semibold leading-6">Continue with Google</span>
                </a>
            </div>

            <div className="relative mb-6">
                <div className="absolute inset-0 flex items-center">
                    <div className="w-full border-t border-gray-200 dark:border-gray-700"></div>
                </div>
                <div className="relative flex justify-center text-sm">
                    <span className="bg-white dark:bg-gray-900 px-4 text-gray-500 dark:text-gray-400">Or log in with email</span>
                </div>
            </div>

            <form onSubmit={submit}>
                <div>
                    <InputLabel htmlFor="email" value="Email" />

                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full px-4 py-3 rounded-xl"
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="you@example.com"
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
                        className="mt-1 block w-full px-4 py-3 rounded-xl"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                        placeholder="••••••••"
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="mt-4 block">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData(
                                    'remember',
                                    (e.target.checked || false) as false,
                                )
                            }
                        />
                        <span className="ms-2 text-sm text-gray-600 dark:text-gray-400">
                            Remember me
                        </span>
                    </label>
                </div>

                <div className="mt-6">
                    <PrimaryButton className="w-full justify-center py-3 rounded-xl text-base font-semibold" disabled={processing}>
                        Log in
                    </PrimaryButton>

                    {canResetPassword && (
                        <div className="mt-4 text-center">
                            <Link
                                href={route('password.request')}
                                className="rounded-md text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
                            >
                                Forgot your password?
                            </Link>

                            <div className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Don't have an account?{' '}
                                <Link
                                    href={route('register')}
                                    className="font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                                >
                                    Sign up
                                </Link>
                            </div>
                        </div>
                    )}
                </div>
            </form>
        </GuestLayout>
    );
}
