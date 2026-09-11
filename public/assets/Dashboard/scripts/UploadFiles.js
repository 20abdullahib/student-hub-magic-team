/* 
*************************************************************
***********  ADD BY abdullah.ibrahiim@yahoo.com *************
***********  https://20abdullah.serv00.net/ *****************
*************************************************************
*/ 



// Constants
const RETRY_DELAY = 1000;
const CHUNK_SIZE = 8 * 1024 * 1024;
const LARGE_FILE_THRESHOLD = 150 * 1024 * 1024;

// DOM Elements
const domElements = {
  uploadButton: document.getElementById("uploadButton"),
  fileInput: document.getElementById("fileInput"),
  subjectSelect: document.getElementById("subject"),
  fileList: document.getElementById("fileList"),
  resetButton: document.getElementById("resetButton"),
  csrfToken: document.querySelector('meta[name="csrf-token"]').content
};

// Service: Dropbox API Interactions
const DropboxService = (() => {
  let client = null;

  return {
    initializeClient: (accessToken) => {
      client = new Dropbox.Dropbox({ accessToken });
      return client;
    },

    uploadFile: async (file, filePath) => {
      // mode 'overwrite' → retrying a failed upload (e.g. shared-link error)
      // replaces the file instead of failing with a 409 conflict.
      return client.filesUpload({
        path: filePath,
        contents: file,
        mode: { '.tag': 'overwrite' },
      });
    },

    uploadLargeFile: async (file, filePath) => {
      let offset = 0;
      const fileSize = file.size;
      
      const sessionStart = await client.filesUploadSessionStart({
        close: false,
        contents: file.slice(offset, offset + CHUNK_SIZE),
      });
      
      let sessionId = sessionStart.result.session_id;
      offset += CHUNK_SIZE;

      while (offset < fileSize) {
        const chunk = file.slice(offset, offset + CHUNK_SIZE);
        await client.filesUploadSessionAppendV2({
          cursor: { session_id: sessionId, offset },
          contents: chunk,
        });
        offset += CHUNK_SIZE;
      }

      return client.filesUploadSessionFinish({
        cursor: { session_id: sessionId, offset: fileSize },
        commit: {
          path: filePath,
          mode: { '.tag': 'overwrite' },
          autorename: false,
          mute: false,
        },
      });
    },

    createSharedLink: async (filePath) => {
      const describe = (error) =>
        error?.error?.error_summary || error?.error || error?.message || String(error);

      try {
        // Create with DEFAULT settings first — most compatible: requesting
        // 'public' visibility explicitly can 400 on account/app configurations
        // that restrict it, and defaults are usually public anyway.
        const response = await client.sharingCreateSharedLinkWithSettings({
          path: filePath,
        });
        return response.result;
      } catch (error) {
        if (error.status === 409) {
          // Link already exists for this path → fetch it.
          const response = await client.sharingListSharedLinks({
            path: filePath,
            direct_only: true,
          });
          return response.result.links[0] || null;
        }
        // e.g. missing scope: "Your app ... does not have the required scope
        // 'sharing.write'" → surface the exact Dropbox reason, not a bare 400.
        throw new Error(`Dropbox: ${describe(error)}`);
      }
    },

    getSpaceUsage: async () => {
      const response = await client.usersGetSpaceUsage();
      return {
        allocated: response.result.allocation.allocated,
        used: response.result.used
      };
    }
  };
})();

// Service: Network Requests
const NetworkService = (() => {
  // Error that must NOT be retried (4xx — bad token, validation, permissions...).
  class FatalRequestError extends Error {}

  const fetchWithRetry = async (url, options, retries = 3) => {
    for (let i = 0; i < retries; i++) {
      try {
        const response = await fetch(url, options);
        if (response.ok) return response;

        let message = `Request failed with ${response.status}`;
        try {
          const body = await response.json();
          message = body?.error || body?.message || message;
        } catch (_) { /* body was not JSON */ }

        // 4xx (except 429) won't succeed on retry — fail immediately with the real reason.
        if (response.status >= 400 && response.status < 500 && response.status !== 429) {
          throw new FatalRequestError(message);
        }

        throw new Error(message);
      } catch (error) {
        if (error instanceof FatalRequestError) throw error;
        if (i === retries - 1) throw error;
        await new Promise(resolve => setTimeout(resolve, RETRY_DELAY));
      }
    }
    throw new Error('Request failed after retries');
  };

  return {
    getAccessToken: async (accountId) => {
      const response = await fetchWithRetry(
        `/dropbox/access-token?account_id=${accountId}`,
        { headers: { Accept: 'application/json' } }
      );
      return response.json().then(data => data.access_token);
    },

    sendFileDetails: async (fileData) => {
      return fetchWithRetry('/dashboard/dropbox/files/store-details', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': domElements.csrfToken
        },
        body: JSON.stringify(fileData)
      });
    },

    updateAccountSpace: async (accountId, remainingSpace) => {
      return fetchWithRetry('/dashboard/dropbox/account/update', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': domElements.csrfToken
        },
        body: JSON.stringify({
          account_id: accountId,
          remaining_storage: remainingSpace
        })
      });
    },

    getAvailableAccounts: async (subjectId) => {
      const response = await fetchWithRetry(
        `/dashboard/dropbox/files/accounts?subject_id=${subjectId}`,
        { headers: { 'X-CSRF-TOKEN': domElements.csrfToken } }
      );
      return response.json();
    }
  };
})();

// Module: File List Manager
const FileListManager = (() => {
  const fileMap = new Map();

  const getFileId = (file) => 
    `${file.name}-${file.size}-${file.lastModified}`;

  return {
    createFileItem: (file) => {
      const fileId = getFileId(file);
      if (fileMap.has(fileId)) return null;

      const item = document.createElement('div');
      item.className = 'file-item';
      item.innerHTML = `
        <span class="filename">${file.name}</span>
        <span class="status-text">Pending...</span>
      `;
      
      domElements.fileList.appendChild(item);
      fileMap.set(fileId, item);
      return item;
    },

    updateStatus: (file, status) => {
      const fileId = getFileId(file);
      const item = fileMap.get(fileId);
      if (item) {
        item.querySelector('.status-text').textContent = status;
      }
    },

    removeFileItem: (file) => {
      const fileId = getFileId(file);
      const item = fileMap.get(fileId);
      if (item) {
        setTimeout(() => {
          item.remove();
          fileMap.delete(fileId);
        }, 2000);
      }
    },

    clearAll: () => {
      fileMap.clear();
      domElements.fileList.innerHTML = '';
    }
  };
})();

// Sanitize a single Dropbox path segment: trim and drop characters that are
// illegal in file/folder names (subject names are Arabic — Unicode is fine,
// only separators like / \ : ? * " < > | are dangerous).
const sanitizePathSegment = (segment) =>
  String(segment ?? '')
    .replace(/[\\/:?*"<>|]/g, '')
    .replace(/\s+/g, ' ')
    .trim();

// Standardized upload structure: /{Department}/{Subject}/[subfolders]/{file}
const buildDropboxPath = (departmentName, subjectName, relativePath, fileName) => {
  const subFolders = String(relativePath ?? '')
    .split('/')
    .map(sanitizePathSegment)
    .filter(Boolean);

  const segments = [
    sanitizePathSegment(departmentName),
    sanitizePathSegment(subjectName),
    ...subFolders,
    sanitizePathSegment(fileName),
  ].filter(Boolean);

  return '/' + segments.join('/');
};

// Controller: Upload Management
const UploadController = (() => {
  const updateAccountSpace = async (client, accountId) => {
    try {
      const { allocated, used } = await DropboxService.getSpaceUsage();
      const remainingSpace = allocated - used;
      
      await NetworkService.updateAccountSpace(accountId, remainingSpace);
    //   console.log(`Updated account ${accountId} remaining space to ${remainingSpace} bytes`);
    } catch (error) {
      console.error('Space update failed:', error);
    }
  };

  const selectAccountWithSpace = async (accounts, requiredSize) => {
    const failures = [];
    let spaceChecked = false;

    for (const account of accounts) {
      const label = account.email || `#${account.id}`;
      try {
        const accessToken = await NetworkService.getAccessToken(account.id);
        const client = DropboxService.initializeClient(accessToken);
        const { allocated, used } = await DropboxService.getSpaceUsage();
        spaceChecked = true;

        if (allocated - used >= requiredSize) {
          return { account, accessToken };
        }

        console.warn(`Account ${label}: only ${allocated - used} bytes free, ${requiredSize} needed`);
      } catch (error) {
        failures.push(`Account ${label}: ${error.message}`);
        console.error(`Account check failed for ${label}:`, error);
      }
    }

    // Every account failed its check (invalid token, missing scope, ...) —
    // report the real reasons instead of the misleading "Insufficient space".
    if (failures.length === accounts.length) {
      throw new Error(failures.join(' | '));
    }
    if (!spaceChecked) {
      throw new Error(failures.join(' | ') || 'No Dropbox account could be checked');
    }

    // At least one account was checked fine but none had enough free space.
    return null;
  };

  const processFileUpload = async (file, subjectId, subjectName, relativePath = '') => {
    const accounts = await NetworkService.getAvailableAccounts(subjectId);
    if (!accounts.length) throw new Error('No available accounts');

    let accountInfo;
    try {
      accountInfo = await selectAccountWithSpace(accounts, file.size);
    } catch (error) {
      // All accounts failed their space/token checks — surface the real reason.
      throw new Error(`Upload aborted — ${error.message}`);
    }
    if (!accountInfo) {
      const mb = (file.size / (1024 * 1024)).toFixed(1);
      throw new Error(`Insufficient space: no Dropbox account of this department has ${mb} MB free`);
    }

    const { account, accessToken } = accountInfo;
    const client = DropboxService.initializeClient(accessToken);
    const departmentName = account.department_name || accounts[0]?.department_name || '';
    // Standardized structure: /Department/Subject/[subfolders]/file
    const filePath = buildDropboxPath(departmentName, subjectName, relativePath, file.name);

    try {
      FileListManager.updateStatus(file, 'Uploading...');
      
      await (file.size > LARGE_FILE_THRESHOLD
        ? DropboxService.uploadLargeFile(file, filePath)
        : DropboxService.uploadFile(file, filePath));

      const sharedLink = await DropboxService.createSharedLink(filePath);
      if (!sharedLink?.url) throw new Error('Failed to get shared link');
      
      await NetworkService.sendFileDetails({
        name: file.name,
        path: filePath,
        size: file.size,
        subject_id: subjectId,
        dropbox_account_id: account.id,
        link: sharedLink.url,
        file_id: sharedLink.url.split('/scl/fi/')[1]?.split('/')[0] || '',
        rlkey: sharedLink.url.split('rlkey=')[1]?.split('&')[0] || ''
      });

      await updateAccountSpace(client, account.id);
      FileListManager.updateStatus(file, 'Uploaded ✔️');
      return true;
    } catch (error) {
      const summary = error.message || String(error);
      console.error(`Upload failed for ${file.name}:`, summary, error);
      FileListManager.updateStatus(file, `Failed ❌ ${summary.slice(0, 140)}`);
      return false;
    } finally {
      FileListManager.removeFileItem(file);
    }
  };

  return { processFileUpload };
})();

// Event Handlers
const setupEventListeners = () => {
  const handleUpload = async () => {
    try {
      const files = domElements.fileInput.files;
      const subjectId = domElements.subjectSelect.value;
      const subjectName = domElements.subjectSelect.selectedOptions[0]?.text;

      if (!subjectId || !files.length) {
        alert(!subjectId ? 'Please select a subject' : 'Please select files');
        return;
      }

      domElements.uploadButton.disabled = true;
      let successCount = 0;
      let failureCount = 0;

      for (const file of files) {
        const relativePath = file.webkitRelativePath?.split('/').slice(0, -1).join('/') || '';
        const success = await UploadController.processFileUpload(
          file, subjectId, subjectName, relativePath
        );
        success ? successCount++ : failureCount++;
      }

      alert(`Uploads completed: ${successCount} successful, ${failureCount} failed`);

    } catch (error) {
      console.error('Upload process failed:', error);
      alert(`Upload process failed: ${error.message}`);
    } finally {
      domElements.uploadButton.disabled = false;
    }
  };

  const resetForm = () => {
    domElements.fileInput.value = '';
    domElements.subjectSelect.value = '';
    FileListManager.clearAll();
  };

  domElements.uploadButton.addEventListener('click', handleUpload);
  domElements.resetButton.addEventListener('click', resetForm);
  domElements.fileInput.addEventListener('change', () => {
    FileListManager.clearAll();
    Array.from(domElements.fileInput.files).forEach(FileListManager.createFileItem);
  });
};

// Initialize Application
setupEventListeners();