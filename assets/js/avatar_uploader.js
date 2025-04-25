/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */
class AvatarUploader {
    static xhrPending = false;

    static resizeImageAsync = async function (file, maxWidth = 1500, maxHeight = 1500) {
        const reader = new FileReader();

        return new Promise((resolve, reject) => {
            reader.onload = (event) => {
                const img = new Image();
                img.onload = function () {
                    let width = img.width;
                    let height = img.height;

                    // Calculate new dimensions while maintaining aspect ratio
                    if (width > maxWidth || height > maxHeight) {
                        if (width > height) {
                            height *= maxWidth / width;
                            width = maxWidth;
                        } else {
                            width *= maxHeight / height;
                            height = maxHeight;
                        }
                    }

                    // Create a canvas and draw the resized image
                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;

                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    // Convert the canvas to a Blob
                    canvas.toBlob(
                        (blob) => {
                            resolve(blob); // Pass the resized Blob
                        },
                        file.type,
                        1 // Quality parameter (1 is max quality for formats like JPEG)
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
    }

    static updateFileInput = async function (fileInput, maxWidth = 1500, maxHeight = 1500) {
        try {
            const file = fileInput.files[0]; // Get the selected file
            const resizedBlob = await AvatarUploader.resizeImageAsync(file, maxWidth, maxHeight);

            // Create a new File object
            const resizedFile = new File([resizedBlob], 'resized-image.jpg', {type: resizedBlob.type});

            // Create a DataTransfer object to update the file input
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(resizedFile);

            // Replace the files in the file input
            fileInput.files = dataTransfer.files;

            console.log('File input updated with resized image:', fileInput.files);
        } catch (error) {
            console.error('Error resizing or updating file input:', error);
        }
    }

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
