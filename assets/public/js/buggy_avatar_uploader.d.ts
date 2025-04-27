declare class BuggyAvatarUploader {
    private xhrPending;
    private readonly errors;
    constructor();
    resizeImage(file: File, maxWidth?: number, maxHeight?: number): Promise<Blob>;
    addError(error: Error): void;
    hasErrors(): boolean;
    getErrors(): Error[];
    updateFileInput(fileInput: HTMLInputElement, maxWidth?: number, maxHeight?: number, acceptedFileTypes?: string[]): Promise<void>;
    validateFileType(file: File, acceptedFileTypes: string[]): boolean;
    rotateImage(htmlImageElement: HTMLImageElement, degrees: number, apiUrl: string): Promise<string>;
}
