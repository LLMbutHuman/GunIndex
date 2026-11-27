for (const button of document.querySelectorAll('[data-setup-copy]')) {
  const container = button.closest('.setup-command');
  const code = container.querySelector('code');
  const status = container.querySelector('[role="status"]');
  button.hidden = false;
  button.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(code.textContent);
      status.textContent = status.dataset.copied;
    } catch {
      const selection = window.getSelection();
      if (selection) selection.selectAllChildren(code);
      status.textContent = status.dataset.copyFailed;
    }
  });
}
