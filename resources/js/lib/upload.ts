/** "2048 KB" -> "2 MB". Slot limits come from the server (UploadLimits::MAX_FILE_KB). */
export function formatMaxSize(maxKb: number): string {
    const mb = maxKb / 1024;

    return `${Number.isInteger(mb) ? mb : mb.toFixed(1)} MB`;
}

/** Same wording as the server's validation message. */
export function fileTooLargeMessage(maxKb: number): string {
    return `File is too large. Maximum size is ${formatMaxSize(maxKb)}.`;
}
