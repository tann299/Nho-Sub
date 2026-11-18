(() => {
  const button = document.getElementById('create-tiktok-qr');
  if (!button) return;
  const dialog = document.getElementById('tiktok-qr-dialog');
  const image = document.getElementById('tiktok-qr-image');
  const status = document.getElementById('tiktok-qr-status');
  let timer;
  let controller;
  let generation = 0;

  const request = async (url, options = {}) => {
    const response = await fetch(url, {
      ...options,
      signal: controller.signal,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      credentials: 'same-origin',
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || 'Chưa kết nối được Napxu. Vui lòng thử lại.');
    return data;
  };

  const stop = () => {
    generation++;
    clearTimeout(timer);
    controller?.abort();
    image.hidden = true;
    image.removeAttribute('src');
    button.disabled = false;
  };
  dialog.addEventListener('close', stop);
  document.getElementById('close-tiktok-qr').addEventListener('click', () => dialog.close());
  window.addEventListener('pagehide', stop);

  button.addEventListener('click', async () => {
    stop();
    const current = generation;
    controller = new AbortController();
    button.disabled = true;
    status.textContent = 'Đang tạo mã QR…';
    dialog.showModal();
    try {
      const qr = await request(button.dataset.endpoint, { method: 'POST', body: '{}' });
      if (generation !== current || !dialog.open) return;
      image.src = qr.qrImage;
      image.hidden = false;
      status.textContent = 'Quét mã và xác nhận trên điện thoại.';
      const poll = async () => {
        if (!dialog.open || generation !== current) return;
        if (Date.now() / 1000 >= qr.expiresAt) {
          status.textContent = 'Mã QR hết hạn. Đóng và tạo mã mới.';
          image.hidden = true;
          return;
        }
        try {
          const data = await request(`${button.dataset.pollBase}/${encodeURIComponent(qr.sessionId)}`);
          if (generation !== current || !dialog.open) return;
          if (data.status === 'done') {
            status.textContent = 'Đã liên kết TikTok. Đang cập nhật danh sách…';
            window.location.reload();
            return;
          }
          if (data.status === 'expired' || data.status === 'error') {
            status.textContent = data.message || 'Mã QR hết hạn. Đóng và tạo mã mới.';
            image.hidden = true;
            return;
          }
          status.textContent = data.status === 'scanned' ? 'Đã quét. Xác nhận đăng nhập trên điện thoại.' : 'Đang chờ quét mã QR.';
          timer = setTimeout(poll, 3000);
        } catch (error) {
          if (error.name !== 'AbortError') status.textContent = error.message;
        }
      };
      timer = setTimeout(poll, 3000);
    } catch (error) {
      if (error.name !== 'AbortError') status.textContent = error.message;
    }
  });
})();
