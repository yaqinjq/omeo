<script>
(function () {
  const alertBox = document.getElementById('geoAlert');
  const distanceInfo = document.getElementById('distanceInfo');
  const gpsStateText = document.getElementById('gpsStateText');
  const gpsAccuracyText = document.getElementById('gpsAccuracyText');
  const gpsSampleInfo = document.getElementById('gpsSampleInfo');
  const outletCoordinatesText = document.getElementById('outletCoordinatesText');
  const userCoordinatesText = document.getElementById('userCoordinatesText');
  const deviceHintBox = document.getElementById('deviceHintBox');
  const gpsSummaryCards = document.querySelectorAll('[data-gps-summary]');
  const evidenceSummaryCards = document.querySelectorAll('[data-evidence-summary]');
  const forms = document.querySelectorAll('.attendance-form');
  const outletLat = Number(@json($currentOutlet?->latitude));
  const outletLng = Number(@json($currentOutlet?->longitude));
  const accuracyLimit = Number(@json($accuracyLimit));
  const radiusLimit = Number(@json($radiusLimit));
  const cameraModal = document.getElementById('cameraCaptureModal');
  const cameraVideo = document.getElementById('cameraVideo');
  const cameraCanvas = document.getElementById('cameraCanvas');
  const cameraLabel = document.getElementById('cameraCaptureLabel');
  const captureBtn = document.getElementById('captureCameraBtn');
  const retryBtn = document.getElementById('retryCameraBtn');
  const closeCameraBtn = document.getElementById('closeCameraModal');
  const checkGpsBtn = document.getElementById('checkGpsBtn');
  const retryGpsBtn = document.getElementById('retryGpsBtn');
  let activeStream = null;
  let activeForm = null;
  let activeKind = null;
  let lastBestLocation = null;

  function haversine(lat1, lon1, lat2, lon2) {
    const R = 6371000;
    const toRad = (value) => (value * Math.PI) / 180;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
  }

  function delay(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  function showError(message) {
    if (!alertBox) return;
    alertBox.textContent = message;
    alertBox.classList.remove('hidden');
  }

  function clearError() {
    if (!alertBox) return;
    alertBox.textContent = '';
    alertBox.classList.add('hidden');
  }

  function setSummaryCardState(cards, options) {
    cards.forEach((card) => {
      const titleNode = card.querySelector('h3');
      const subtitleNode = card.querySelector('p');
      const badgeNode = card.querySelector('span');

      if (titleNode && options.title) {
        titleNode.textContent = options.title;
      }

      if (subtitleNode && options.subtitle) {
        subtitleNode.textContent = options.subtitle;
      }

      if (badgeNode && options.badge) {
        badgeNode.textContent = options.badge;
      }
    });
  }

  function setGpsCopy(message) {
    if (gpsStateText) gpsStateText.textContent = message;
    document.querySelectorAll('[data-gps-copy]').forEach((node) => {
      node.textContent = message;
    });
    setSummaryCardState(gpsSummaryCards, {
      title: message,
      subtitle: `Radius outlet ${radiusLimit} meter. Akurasi maksimal ${accuracyLimit} meter.`,
      badge: 'GPS',
    });
  }

  function setEvidenceCopy(form) {
    const ok = hasEvidence(form, 'selfie') && hasEvidence(form, 'environment');
    const node = form.querySelector('[data-evidence-copy]');
    if (node) node.textContent = ok ? 'lengkap' : 'belum lengkap';
    setSummaryCardState(evidenceSummaryCards, {
      title: ok ? 'Siap dipakai' : 'Belum lengkap',
      subtitle: ok
        ? 'Selfie live dan foto lingkungan sudah siap untuk dikirim bersama presensi.'
        : 'Selfie live dan foto lingkungan wajib diambil langsung dari kamera browser.',
      badge: ok ? 'Siap' : 'Live',
    });
  }

  function restoreSubmitButton(form) {
    const submitButton = form?.querySelector('button[type="submit"]');
    if (!submitButton) return;
    submitButton.disabled = false;
    submitButton.textContent = submitButton.dataset.originalText || submitButton.textContent;
  }

  function stopStream() {
    if (activeStream) {
      activeStream.getTracks().forEach((track) => track.stop());
      activeStream = null;
    }
  }

  async function openCamera(form, kind) {
    activeForm = form;
    activeKind = kind;

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      showError('Browser ini belum mendukung live camera. Untuk iPhone, gunakan Safari versi terbaru dan izinkan kamera browser.');
      return;
    }

    clearError();
    stopStream();

    const facingMode = kind === 'selfie' ? 'user' : 'environment';
    try {
      activeStream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: facingMode }, width: { ideal: 1280 }, height: { ideal: 720 } },
        audio: false,
      });
      cameraVideo.srcObject = activeStream;
      cameraLabel.textContent = kind === 'selfie'
        ? 'Kamera depan aktif. Pastikan wajah terlihat jelas.'
        : 'Kamera belakang aktif. Arahkan ke area kerja atau lingkungan sekitar.';
      cameraModal.classList.remove('hidden');
      cameraModal.classList.add('flex');
    } catch (error) {
      showError('Kamera tidak dapat diakses. Izinkan kamera pada browser lalu coba lagi. Jika memakai iPhone, cek Safari > Camera > Allow.');
    }
  }

  function closeCameraModal() {
    cameraModal.classList.add('hidden');
    cameraModal.classList.remove('flex');
    stopStream();
  }

  function formatBytes(bytes) {
    if (!bytes || Number.isNaN(bytes)) return '0 KB';
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
  }

  function buildCompressedSnapshot(video, canvas, kind) {
    const sourceWidth = video.videoWidth || 1280;
    const sourceHeight = video.videoHeight || 720;
    const maxWidth = kind === 'selfie' ? 960 : 1280;
    const scale = Math.min(1, maxWidth / sourceWidth);
    const targetWidth = Math.max(320, Math.round(sourceWidth * scale));
    const targetHeight = Math.max(240, Math.round(sourceHeight * scale));

    canvas.width = targetWidth;
    canvas.height = targetHeight;
    const context = canvas.getContext('2d', { alpha: false });
    context.drawImage(video, 0, 0, targetWidth, targetHeight);

    let quality = kind === 'selfie' ? 0.82 : 0.78;
    let dataUrl = canvas.toDataURL('image/jpeg', quality);

    while (dataUrl.length > 2400000 && quality > 0.5) {
      quality -= 0.08;
      dataUrl = canvas.toDataURL('image/jpeg', quality);
    }

    return dataUrl;
  }

  function setCaptureResult(form, kind, dataUrl) {
    const hiddenField = form.querySelector(`.camera-data-field[data-kind="${kind}"]`);
    const preview = form.querySelector(`.camera-preview[data-preview="${kind}"]`);
    const placeholder = form.querySelector(`.camera-placeholder[data-placeholder="${kind}"]`);
    const badge = form.querySelector(`[data-status-badge="${kind}"]`);
    const captureMode = form.querySelector('.capture-mode-field');
    const sizeLabel = form.querySelector(`[data-size-label="${kind}"]`);
    const estimatedBytes = Math.round((dataUrl.length * 3) / 4);

    if (hiddenField) hiddenField.value = dataUrl;
    if (captureMode) captureMode.value = 'live_camera';
    if (preview) {
      preview.src = dataUrl;
      preview.classList.remove('hidden');
    }
    if (placeholder) {
      placeholder.classList.add('hidden');
    }
    if (badge) {
      badge.textContent = 'Sudah capture';
      badge.className = 'rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700';
    }
    if (sizeLabel) {
      sizeLabel.textContent = `Ukuran capture: ${formatBytes(estimatedBytes)}`;
    }

    setEvidenceCopy(form);
  }

  function resetCapture(form, kind, reopen) {
    const hiddenField = form.querySelector(`.camera-data-field[data-kind="${kind}"]`);
    const preview = form.querySelector(`.camera-preview[data-preview="${kind}"]`);
    const placeholder = form.querySelector(`.camera-placeholder[data-placeholder="${kind}"]`);
    const badge = form.querySelector(`[data-status-badge="${kind}"]`);
    const sizeLabel = form.querySelector(`[data-size-label="${kind}"]`);

    if (hiddenField) hiddenField.value = '';
    if (preview) {
      preview.src = '';
      preview.classList.add('hidden');
    }
    if (placeholder) {
      placeholder.classList.remove('hidden');
    }
    if (badge) {
      badge.textContent = 'Belum capture';
      badge.className = 'rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600';
    }
    if (sizeLabel) {
      sizeLabel.textContent = 'Ukuran capture: belum ada';
    }

    setEvidenceCopy(form);

    if (reopen) {
      openCamera(form, kind);
    }
  }

  function hasEvidence(form, kind) {
    const hiddenField = form.querySelector(`.camera-data-field[data-kind="${kind}"]`);
    return Boolean(hiddenField?.value);
  }

  function formatCoordinate(value) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
      return '-';
    }
    return Number(value).toFixed(7);
  }

  function computeDistance(lat, lng) {
    if (Number.isNaN(outletLat) || Number.isNaN(outletLng) || Number.isNaN(Number(lat)) || Number.isNaN(Number(lng))) {
      return null;
    }
    return haversine(outletLat, outletLng, Number(lat), Number(lng));
  }

  function updateDistance(lat, lng, accuracy, sampleMeta) {
    const distance = computeDistance(lat, lng);
    if (distanceInfo) {
      distanceInfo.textContent = distance === null ? '-' : distance.toFixed(2);
    }
    if (gpsAccuracyText) {
      gpsAccuracyText.textContent = accuracy || accuracy === 0 ? `${Math.round(accuracy)} meter` : '-';
    }
    if (userCoordinatesText) {
      userCoordinatesText.textContent = `${formatCoordinate(lat)}, ${formatCoordinate(lng)}`;
    }
    if (outletCoordinatesText && !outletCoordinatesText.textContent.trim()) {
      outletCoordinatesText.textContent = `${formatCoordinate(outletLat)}, ${formatCoordinate(outletLng)}`;
    }
    if (gpsSampleInfo && sampleMeta) {
      gpsSampleInfo.textContent = sampleMeta;
    }
    return distance;
  }

  function requestLocationPromise() {
    return new Promise((resolve, reject) => {
      if (!navigator.geolocation) {
        reject(new Error('unsupported'));
        return;
      }

      navigator.geolocation.getCurrentPosition(resolve, reject, {
        enableHighAccuracy: true,
        timeout: 12000,
        maximumAge: 0,
      });
    });
  }

  function scoreCandidate(candidate) {
    const distanceScore = candidate.distance === null ? 999999 : candidate.distance;
    const accuracyScore = Number.isFinite(candidate.accuracy) ? candidate.accuracy : 999999;
    return (distanceScore * 1000) + accuracyScore;
  }

  function chooseBestCandidate(candidates) {
    return [...candidates].sort((left, right) => {
      const scoreDiff = scoreCandidate(left) - scoreCandidate(right);
      if (scoreDiff !== 0) return scoreDiff;
      return (left.sampleIndex || 0) - (right.sampleIndex || 0);
    })[0] || null;
  }

  async function collectBestLocation(sampleCount) {
    const candidates = [];
    let lastError = null;

    for (let index = 0; index < sampleCount; index += 1) {
      setGpsCopy(`mengambil lokasi ${index + 1}/${sampleCount}...`);
      if (gpsSampleInfo) {
        gpsSampleInfo.textContent = `Mengambil sampel lokasi ${index + 1} dari ${sampleCount}`;
      }

      try {
        const position = await requestLocationPromise();
        const lat = Number(position.coords.latitude);
        const lng = Number(position.coords.longitude);
        const accuracy = Number(position.coords.accuracy);
        const distance = computeDistance(lat, lng);
        candidates.push({ lat, lng, accuracy, distance, sampleIndex: index + 1 });
        updateDistance(lat, lng, accuracy, `Sampel ${index + 1}/${sampleCount} diterima`);
      } catch (error) {
        lastError = error;
      }

      if (index < sampleCount - 1) {
        await delay(700);
      }
    }

    if (!candidates.length) {
      throw lastError || new Error('location_failed');
    }

    const best = chooseBestCandidate(candidates);
    lastBestLocation = { ...best, sampleCount: candidates.length };
    updateDistance(best.lat, best.lng, best.accuracy, `Memakai sampel terbaik dari ${candidates.length} pembacaan`);
    return { best, candidates };
  }

  function gpsReadinessLabel(accuracy, distance) {
    if (accuracy === null || accuracy === undefined || Number.isNaN(Number(accuracy))) {
      return 'belum dicek';
    }

    const rounded = Math.round(Number(accuracy));
    if (rounded > accuracyLimit) {
      return `akurasi terlalu rendah (${rounded} meter, maks ${accuracyLimit} meter)`;
    }

    if (distance !== null && Number(distance) > radiusLimit) {
      return `di luar radius outlet (${Number(distance).toFixed(2)} meter)`;
    }

    return `siap submit (${rounded} meter)`;
  }

  function describeLocationError(error) {
    if (!error || error.message === 'unsupported') {
      return {
        state: 'tidak didukung browser',
        message: 'Browser tidak mendukung geolocation. Gunakan browser modern.',
      };
    }

    if (error.code === 1) {
      return {
        state: 'izin ditolak',
        message: 'Izin lokasi ditolak. Aktifkan Location Permission browser lalu coba lagi.',
      };
    }

    if (error.code === 3) {
      return {
        state: 'timeout',
        message: 'Pengambilan lokasi timeout. Coba lagi di area terbuka dengan GPS aktif.',
      };
    }

    return {
      state: 'lokasi tidak tersedia',
      message: 'Lokasi tidak tersedia. Pastikan GPS aktif dan izin lokasi browser sudah diizinkan.',
    };
  }

  function showDeviceHint() {
    const ua = navigator.userAgent || '';
    const isMobile = /Android|iPhone|iPad|iPod/i.test(ua);
    const isDesktopLike = !isMobile && /Windows|Macintosh|Linux/i.test(ua);

    if (!deviceHintBox) return;

    if (isDesktopLike) {
      deviceHintBox.textContent = 'Perangkat ini terdeteksi seperti desktop/laptop. Untuk presensi GPS yang lebih stabil, sangat disarankan memakai HP dengan GPS aktif dan mode lokasi presisi.';
      deviceHintBox.classList.remove('hidden');
      return;
    }

    const isIphoneLike = /iPhone|iPad|iPod/i.test(ua);

    if (isIphoneLike) {
      deviceHintBox.textContent = 'Perangkat iPhone terdeteksi. Untuk hasil terbaik, aktifkan Precise Location di Safari dan ulangi cek GPS satu kali bila akurasi masih tinggi.';
      deviceHintBox.classList.remove('hidden');
      return;
    }

    deviceHintBox.classList.add('hidden');
  }

  if (captureBtn) {
    captureBtn.addEventListener('click', function () {
      if (!cameraVideo || !cameraCanvas || !activeForm || !activeKind) return;
      const dataUrl = buildCompressedSnapshot(cameraVideo, cameraCanvas, activeKind);
      setCaptureResult(activeForm, activeKind, dataUrl);
      closeCameraModal();
    });
  }

  if (retryBtn) {
    retryBtn.addEventListener('click', function () {
      if (activeForm && activeKind) {
        openCamera(activeForm, activeKind);
      }
    });
  }

  if (closeCameraBtn) {
    closeCameraBtn.addEventListener('click', closeCameraModal);
  }

  document.querySelectorAll('.open-camera-btn').forEach(function (button) {
    button.addEventListener('click', function () {
      const form = button.closest('form');
      const kind = button.dataset.target;
      openCamera(form, kind);
    });
  });

  document.querySelectorAll('.reset-capture-btn').forEach(function (button) {
    button.addEventListener('click', function () {
      const form = button.closest('form');
      const kind = button.dataset.target;
      resetCapture(form, kind, true);
    });
  });

  if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
    showError('Presensi GPS hanya berjalan di HTTPS. Mohon akses via domain HTTPS.');
  }

  forms.forEach(function (form) {
    setEvidenceCopy(form);

    form.addEventListener('submit', async function (event) {
      const submitButton = form.querySelector('button[type="submit"]');
      if (submitButton?.disabled) {
        event.preventDefault();
        return;
      }

      if (!hasEvidence(form, 'selfie')) {
        event.preventDefault();
        showError('Selfie belum diambil. Aktifkan kamera depan lalu ambil snapshot terlebih dahulu.');
        return;
      }

      if (!hasEvidence(form, 'environment')) {
        event.preventDefault();
        showError('Foto lingkungan belum diambil. Aktifkan kamera belakang lalu ambil snapshot terlebih dahulu.');
        return;
      }

      event.preventDefault();
      clearError();
      if (submitButton) {
        submitButton.dataset.originalText = submitButton.dataset.originalText || submitButton.textContent.trim();
        submitButton.disabled = true;
        submitButton.textContent = 'Mengambil lokasi terbaik...';
      }

      const latField = form.querySelector('.lat-field');
      const lngField = form.querySelector('.lng-field');
      const accField = form.querySelector('.acc-field');
      const captureMode = form.querySelector('.capture-mode-field');
      const samplesJsonField = form.querySelector('.samples-json-field');
      const selectedSampleField = form.querySelector('.selected-sample-field');
      if (captureMode) captureMode.value = 'live_camera';

      try {
        const { best, candidates } = await collectBestLocation(4);
        const readinessLabel = gpsReadinessLabel(best.accuracy, best.distance);
        setGpsCopy(`${readinessLabel} dari ${candidates.length} sampel`);

        latField.value = best.lat;
        lngField.value = best.lng;
        accField.value = best.accuracy;
        if (samplesJsonField) samplesJsonField.value = JSON.stringify(candidates.map((candidate) => ({
          sample_index: candidate.sampleIndex,
          lat: candidate.lat,
          lng: candidate.lng,
          accuracy: candidate.accuracy,
          distance: candidate.distance,
        })));
        if (selectedSampleField) selectedSampleField.value = best.sampleIndex || '';

        if (Math.round(Number(best.accuracy)) > accuracyLimit) {
          restoreSubmitButton(form);
          showError(`Akurasi GPS terbaik masih ${Math.round(Number(best.accuracy))} meter. Maksimal ${accuracyLimit} meter agar presensi bisa dikirim.`);
          return;
        }

        if (best.distance !== null && Number(best.distance) > radiusLimit) {
          restoreSubmitButton(form);
          showError(`Lokasi terbaik yang terbaca masih ${Number(best.distance).toFixed(2)} meter dari outlet. Maksimal ${radiusLimit} meter. Coba ambil ulang lokasi atau gunakan HP di area yang lebih terbuka.`);
          return;
        }

        if (submitButton) {
          submitButton.textContent = 'Memproses presensi...';
        }
        form.submit();
      } catch (error) {
        restoreSubmitButton(form);
        const details = describeLocationError(error);
        setGpsCopy(details.state);
        showError(details.message);
      }
    });
  });

  async function runGpsCheck() {
    clearError();

    try {
      const { best, candidates } = await collectBestLocation(4);
      const readinessLabel = gpsReadinessLabel(best.accuracy, best.distance);
      setGpsCopy(`${readinessLabel} dari ${candidates.length} sampel`);

      if (Math.round(Number(best.accuracy)) > accuracyLimit) {
        showError(`Akurasi GPS terbaik masih ${Math.round(Number(best.accuracy))} meter. Maksimal ${accuracyLimit} meter agar submit berhasil.`);
        return;
      }

      if (best.distance !== null && Number(best.distance) > radiusLimit) {
        showError(`Lokasi terbaik yang terbaca masih ${Number(best.distance).toFixed(2)} meter dari outlet. Maksimal ${radiusLimit} meter. Coba ambil ulang lokasi atau gunakan HP di area yang lebih terbuka.`);
      }
    } catch (error) {
      const details = describeLocationError(error);
      setGpsCopy(details.state);
      showError(details.message);
    }
  }

  if (checkGpsBtn) {
    checkGpsBtn.addEventListener('click', runGpsCheck);
  }

  if (retryGpsBtn) {
    retryGpsBtn.addEventListener('click', runGpsCheck);
  }

  showDeviceHint();

  if (document.querySelector('.attendance-form[data-scan="in"]') || document.querySelector('.attendance-form[data-scan="out"]')) {
    runGpsCheck();
  }

  const clockEl = document.getElementById('outletClock');
  if (clockEl) {
    setInterval(function () {
      try {
        const now = new Date();
        const text = new Intl.DateTimeFormat('id-ID', {
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit',
          hour12: false,
          timeZone: @json($tz),
        }).format(now);
        clockEl.textContent = text;
      } catch (error) {}
    }, 1000);
  }

  const attendanceLockModal = document.getElementById('attendanceLockModal');
  const attendanceLockModalClose = document.getElementById('attendanceLockModalClose');
  function openAttendanceLockModal() {
    if (!attendanceLockModal) return;
    attendanceLockModal.classList.remove('hidden');
    attendanceLockModal.classList.add('flex');
  }
  function closeAttendanceLockModal() {
    if (!attendanceLockModal) return;
    attendanceLockModal.classList.add('hidden');
    attendanceLockModal.classList.remove('flex');
  }
  if (attendanceLockModalClose) {
    attendanceLockModalClose.addEventListener('click', closeAttendanceLockModal);
  }
  if (attendanceLockModal) {
    openAttendanceLockModal();
  }
  const modal = document.getElementById('tourPresensiModal');
  const closeBtn = document.getElementById('tourPresensiClose');
  const closeFooterBtn = document.getElementById('tourPresensiCloseFooter');
  const openBtn = document.getElementById('btnTourPresensi');
  const key = 'tour_presensi_seen_v6';

  function openTour() {
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }

  function closeTour() {
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    localStorage.setItem(key, '1');
  }

  if (openBtn) openBtn.addEventListener('click', openTour);
  if (closeBtn) closeBtn.addEventListener('click', closeTour);
  if (closeFooterBtn) closeFooterBtn.addEventListener('click', closeTour);
  if (!attendanceLockModal && !localStorage.getItem(key)) openTour();
})();
</script>



