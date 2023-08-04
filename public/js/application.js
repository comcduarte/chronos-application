document.querySelectorAll('[data-bs-toggle="popover"]')
    .forEach(popover => {
      new bootstrap.Popover(popover)
    })