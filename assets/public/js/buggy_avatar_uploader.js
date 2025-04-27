"use strict";
class BuggyAvatarUploader {
    constructor() {
        this.xhrPending = false;
        this.errors = [];
        this.errors = [];
    }
    async resizeImage(file, maxWidth = 1500, maxHeight = 1500) {
        if (!file.type.startsWith('image/')) {
            throw new Error('Provided file is not an image.');
        }
        const reader = new FileReader();
        return new Promise((resolve, reject) => {
            reader.onload = (event) => {
                const img = new Image();
                img.onload = () => {
                    let width = img.width;
                    let height = img.height;
                    if (width > maxWidth || height > maxHeight) {
                        if (width > height) {
                            height *= maxWidth / width;
                            width = maxWidth;
                        }
                        else {
                            width *= maxHeight / height;
                            height = maxHeight;
                        }
                    }
                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    if (!ctx) {
                        reject(new Error('Failed to get canvas context.'));
                        return;
                    }
                    ctx.drawImage(img, 0, 0, width, height);
                    canvas.toBlob((blob) => {
                        if (blob) {
                            resolve(blob);
                        }
                        else {
                            reject(new Error('Failed to create blob.'));
                        }
                    }, file.type, 1);
                };
                img.onerror = () => {
                    reject(new Error('Error loading image.'));
                };
                if (event.target?.result) {
                    img.src = event.target.result;
                }
            };
            reader.onerror = () => {
                reject(new Error('Error reading file.'));
            };
            reader.readAsDataURL(file);
        });
    }
    addError(error) {
        const event = new CustomEvent('AVATAR_UPLOADER_ERROR', {
            detail: {
                error: error.message,
            },
        });
        document.dispatchEvent(event);
        this.errors.push(error);
    }
    hasErrors() {
        return this.errors.length > 0;
    }
    getErrors() {
        return this.errors;
    }
    async updateFileInput(fileInput, maxWidth = 1500, maxHeight = 1500, acceptedFileTypes = ['image/png', 'image/jpg', 'image/jpeg']) {
        try {
            const files = Array.from(fileInput.files || []); // Convert file list to an array
            const dataTransfer = new DataTransfer();
            for (const file of files) {
                try {
                    if (this.validateFileType(file, acceptedFileTypes)) {
                        if (file.type.startsWith('image/')) {
                            try {
                                const resizedBlob = await this.resizeImage(file, maxWidth, maxHeight);
                                const resizedFile = new File([resizedBlob], file.name, { type: resizedBlob.type });
                                dataTransfer.items.add(resizedFile);
                            }
                            catch (err) {
                                console.warn(`Failed to resize ${file.name}:`, err);
                                dataTransfer.items.add(file); // Add the original file if resizing fails
                            }
                        }
                    }
                    else {
                        throw new Error(`The file "${file.name}" has an invalid file type. Allowed file types: ${acceptedFileTypes.join(', ')}.`);
                    }
                }
                catch (err) {
                    // We have to verify err is an
                    // error before using it as one.
                    if (err instanceof Error) {
                        console.error(`Error updating file "${file.name}" with error message: ${err}`);
                        this.addError(err);
                    }
                }
            }
            fileInput.files = dataTransfer.files;
        }
        catch (err) {
            if (err instanceof Error) {
                console.error(`Error updating file input: ${err}`);
                this.addError(err);
            }
        }
    }
    validateFileType(file, acceptedFileTypes) {
        return acceptedFileTypes.includes(file.type);
    }
    async rotateImage(htmlImageElement, degrees, apiUrl) {
        if (this.xhrPending) {
            throw new Error('There is already a pending request!');
        }
        if (!htmlImageElement) {
            this.xhrPending = false;
            throw new Error('Image element not found!');
        }
        this.xhrPending = true;
        try {
            await fetch(apiUrl); // Replace with your API URL
        }
        catch (err) {
            this.xhrPending = false;
            throw new Error('Failed to rotate image.');
        }
        let angle = parseInt(htmlImageElement.getAttribute('data-angle') || '0');
        angle = (angle + degrees) % 360;
        htmlImageElement.dataset.angle = angle.toString();
        htmlImageElement.style.transform = `rotate(${angle}deg)`;
        this.xhrPending = false;
        return 'Image successfully rotated.';
    }
}
//# sourceMappingURL=buggy_avatar_uploader.js.map