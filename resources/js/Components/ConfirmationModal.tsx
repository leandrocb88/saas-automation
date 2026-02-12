import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import DangerButton from '@/Components/DangerButton';
import { useState } from 'react';

interface Props {
    show: boolean;
    title: string;
    content: string;
    confirmText?: string;
    cancelText?: string;
    onClose: () => void;
    onConfirm: () => void;
    processing?: boolean;
}

export default function ConfirmationModal({
    show,
    title,
    content,
    confirmText = 'Confirm',
    cancelText = 'Cancel',
    onClose,
    onConfirm,
    processing = false,
}: Props) {
    return (
        <Modal show={show} onClose={onClose} maxWidth="sm">
            <div className="p-6">
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {title}
                </h2>

                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {content}
                </p>

                <div className="mt-6 flex justify-end">
                    <SecondaryButton onClick={onClose} disabled={processing}>
                        {cancelText}
                    </SecondaryButton>

                    <DangerButton
                        className="ms-3"
                        onClick={onConfirm}
                        disabled={processing}
                    >
                        {confirmText}
                    </DangerButton>
                </div>
            </div>
        </Modal>
    );
}
