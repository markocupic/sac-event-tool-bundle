class AvatarUploader {
    static xhrPending = false;

    static resizeImageAsync = async function (file, maxWidth = 1500, maxHeight = 1500) {
        if (!file.type.startsWith('image/')) {
            throw new Error('Provided file is not an image.');
        }

        const reader = new FileReader();

        return new Promise((resolve, reject) => {
            reader.onload = (event) => {
                const img = new Image();
                img.onload = function () {
                    let width = img.width;
                    let height = img.height;

                    if (width > maxWidth || height > maxHeight) {
                        if (width > height) {
                            height *= maxWidth / width;
                            width = maxWidth;
                        } else {
                            width *= maxHeight / height;
                            height = maxHeight;
                        }
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;

                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    canvas.toBlob(
                        (blob) => {
                            resolve(blob);
                        },
                        file.type,
                        1
                    );
                };

                img.onerror = () => {
                    reject(new Error('Error loading image.'));
                };

                img.src = event.target.result;
            };

            reader.onerror = () => {
                reject(new Error('Error reading file.'));
            };

            reader.readAsDataURL(file);
        });
    };

    static updateFileInput = async function (fileInput, maxWidth = 1500, maxHeight = 1500) {
        try {
            const files = Array.from(fileInput.files); // Convert file list to an array
            const dataTransfer = new DataTransfer();

            for (const file of files) {
                if (file.type.startsWith('image/')) {
                    try {
                        const resizedBlob = await AvatarUploader.resizeImageAsync(file, maxWidth, maxHeight);
                        const resizedFile = new File([resizedBlob], file.name, {type: resizedBlob.type});
                        dataTransfer.items.add(resizedFile);
                    } catch (error) {
                        console.warn(`Failed to resize ${file.name}:`, error);
                        dataTransfer.items.add(file); // Add the original file if resizing fails
                    }
                } else {
                    console.warn(`${file.name} is not an image and was not resized.`);
                    dataTransfer.items.add(file); // Add non-image files unchanged
                }
            }

            fileInput.files = dataTransfer.files;
            console.log('File input updated with resized images:', fileInput.files);
        } catch (error) {
            console.error('Error updating file input:', error);
        }
    };

    static rotateImage = async function (htmlImageElement, degrees, apiUrl) {
        if (AvatarUploader.xhrPending) {
            return new Promise((reject) => {
                reject('There is already a pending request!');
            });
        }

        if (!htmlImageElement) {
            AvatarUploader.xhrPending = false;
            return new Promise((reject) => {
                reject(new Error('Image element not found!'));
            });
        }

        if (htmlImageElement) {
            AvatarUploader.xhrPending = true;

            try {
                const response = await fetch(apiUrl) // Replace with your API URL
            } catch (error) {
                AvatarUploader.xhrPending = false;

                return new Promise((reject) => {
                    reject(new Error('Image element not found!'));
                });
            }

            // Apply rotation using CSS transform
            let angle = htmlImageElement.getAttribute('data-angle') || 0;
            angle = parseInt(angle) + 90;
            angle = angle === 360 ? 0 : angle;
            htmlImageElement.dataset.angle = angle;
            htmlImageElement.style.transform = `rotate(${angle}deg)`;

            AvatarUploader.xhrPending = false;

            return new Promise((resolve) => {
                resolve('Image successfully rotated by 90 degrees.');
            })
        }
    }
}
