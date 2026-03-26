/**
 * Allowed document extensions for lead / VAS / special-request uploads.
 * Keep in sync with App\Rules\AllowedDocumentFile::EXTENSIONS.
 */
export const DOCUMENT_UPLOAD_EXTENSIONS = [
  '.pdf',
  '.doc',
  '.docx',
  '.txt',
  '.xls',
  '.xlsx',
  '.csv',
  '.eml',
  '.msg',
  '.png',
  '.jpg',
  '.jpeg',
  '.gpg',
  '.gif',
  '.webp',
]

export function documentUploadAcceptAttr() {
  return DOCUMENT_UPLOAD_EXTENSIONS.join(',')
}

/** Short label for UI hints (no dots). */
export function documentUploadTypesHint() {
  return 'PDF, DOC, DOCX, TXT, XLS, XLSX, CSV, EML, MSG, PNG, JPG, JPEG, GPG, GIF, WEBP'
}
