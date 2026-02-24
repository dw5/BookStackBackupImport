import {Component} from './component.js';
import {showLoading} from '../services/dom';

export class BackupUpload extends Component {

    protected fileInput!: HTMLInputElement;
    protected uploadButton!: HTMLButtonElement;
    protected fileInfo!: HTMLElement;
    protected form!: HTMLFormElement;
    protected maxSize!: number;
    protected originalButtonHTML!: string;

    setup() {
        this.fileInput = this.$refs.fileInput as HTMLInputElement;
        this.uploadButton = this.$refs.uploadButton as HTMLButtonElement;
        this.fileInfo = this.$refs.fileInfo as HTMLElement;
        this.form = this.$el as HTMLFormElement;
        this.maxSize = Number(this.$opts.maxSize) || 50;
        this.originalButtonHTML = this.uploadButton.innerHTML;

        this.fileInput.addEventListener('change', () => this.fileSelected());
        this.form.addEventListener('submit', () => this.formSubmitted());
    }

    protected fileSelected() {
        const file = this.fileInput.files?.[0];

        if (!file) {
            this.uploadButton.disabled = true;
            this.fileInfo.style.display = 'none';
            this.fileInfo.textContent = '';
            return;
        }

        const fileSizeMB = file.size / (1024 * 1024);
        if (fileSizeMB > this.maxSize) {
            window.$events.emit('error', this.fileInfo.dataset.errorTooLarge || 'File is too large.');
            this.fileInput.value = '';
            this.uploadButton.disabled = true;
            this.fileInfo.style.display = 'none';
            this.fileInfo.textContent = '';
            return;
        }

        this.fileInfo.textContent = file.name;
        this.fileInfo.style.display = '';
        this.uploadButton.disabled = false;
    }

    protected formSubmitted() {
        this.uploadButton.disabled = true;

        const loadingWrap = document.createElement('div');
        loadingWrap.classList.add('inline', 'block');
        showLoading(loadingWrap);
        this.uploadButton.innerHTML = '';
        this.uploadButton.appendChild(loadingWrap);
    }
}
