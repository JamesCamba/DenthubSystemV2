/**
 * Wraps each account page in a panel and pins that page's logo + title
 * above a scrolling content area so the title stays put and never covers the body.
 */
(function () {
    var scriptSrc = document.currentScript && document.currentScript.src;
    var logoSrc = '../assets/images/logobluetransparent.png';
    if (scriptSrc) {
        logoSrc = scriptSrc.replace(/\/assets\/js\/page-shell\.js(?:\?.*)?$/, '/assets/images/logobluetransparent.png');
    }

    function isTitleBar(el) {
        if (!el || !el.classList || !el.classList.contains('d-flex')) return false;
        if (el.closest('.card, .modal, .offcanvas')) return false;
        var kids = el.children;
        if (!kids.length || kids.length > 6) return false;
        for (var i = 0; i < kids.length; i++) {
            if (kids[i].matches('form, table, .card, .row, .alert, .table-responsive')) return false;
        }
        return true;
    }

    function pruneEmpty(node, stopAt) {
        while (node && node !== stopAt && node.nodeType === 1) {
            var parent = node.parentElement;
            var meaningful = false;
            for (var i = 0; i < node.childNodes.length; i++) {
                var child = node.childNodes[i];
                if (child.nodeType === 1) {
                    meaningful = true;
                    break;
                }
                if (child.nodeType === 3 && child.textContent.trim()) {
                    meaningful = true;
                    break;
                }
            }
            if (meaningful) break;
            node.remove();
            node = parent;
        }
    }

    function findLooseTitle(main) {
        var titled = main.querySelector('h1.denthub-page-title, h2.denthub-page-title, h1, h2');
        if (!titled) return null;
        if (titled.closest('.card, .modal, .offcanvas')) {
            var headings = main.querySelectorAll('h1, h2');
            titled = null;
            for (var i = 0; i < headings.length; i++) {
                if (!headings[i].closest('.card, .modal, .offcanvas')) {
                    titled = headings[i];
                    break;
                }
            }
        }
        return titled;
    }

    function liftCardTitle(main) {
        var header = main.querySelector('.card-header h1, .card-header h2, .card-header h3, .card-header h4, .card-header h5');
        if (!header || header.closest('.modal')) return null;
        var cardHeader = header.closest('.card-header');
        var clone = header.cloneNode(true);
        clone.querySelectorAll('[id]').forEach(function (el) { el.removeAttribute('id'); });
        if (cardHeader) {
            var extra = false;
            for (var i = 0; i < cardHeader.children.length; i++) {
                var child = cardHeader.children[i];
                if (child === header) continue;
                if (child.textContent.trim() || child.querySelector('a, button, input, select')) extra = true;
            }
            if (!extra) cardHeader.classList.add('d-none');
        }
        return clone;
    }

    function enhance(main) {
        if (main.querySelector('.denthub-page-shell') || main.dataset.pageShell === 'off') return;

        var title = findLooseTitle(main);
        var headInner = null;
        var subtitle = null;
        var detachParent = null;

        if (title) {
            title.classList.add('denthub-page-title');
            var parent = title.parentElement;
            if (isTitleBar(parent)) {
                headInner = parent;
            } else {
                headInner = title;
                var next = title.nextElementSibling;
                if (next && next.matches('p')) subtitle = next;
            }
            detachParent = headInner.parentElement;
            headInner.remove();
            if (subtitle) subtitle.remove();
            pruneEmpty(detachParent, main);
            title.classList.remove('mb-0', 'mb-1', 'mb-2', 'mb-3', 'mb-4', 'mb-5');
            if (headInner.classList) {
                headInner.classList.remove('mb-0', 'mb-1', 'mb-2', 'mb-3', 'mb-4', 'mb-5');
            }
        } else {
            headInner = liftCardTitle(main);
            if (headInner) {
                headInner.classList.add('denthub-page-title');
                headInner.classList.remove('text-white', 'mb-0', 'mb-1', 'mb-2', 'mb-3', 'mb-4', 'mb-5');
            }
        }

        var shell = document.createElement('div');
        shell.className = 'denthub-page-shell';

        var body = document.createElement('div');
        body.className = 'denthub-page-body';
        while (main.firstChild) body.appendChild(main.firstChild);

        body.querySelectorAll(':scope > .container, :scope > .container-fluid').forEach(function (box) {
            box.classList.remove('py-3', 'py-4', 'py-5');
        });

        if (headInner) {
            var head = document.createElement('header');
            head.className = 'denthub-page-head';

            var mark = document.createElement('div');
            mark.className = 'denthub-page-mark';
            var img = document.createElement('img');
            img.src = logoSrc;
            img.alt = '';
            img.addEventListener('error', function () { mark.hidden = true; });
            mark.appendChild(img);
            head.appendChild(mark);

            if (subtitle) {
                var wrap = document.createElement('div');
                wrap.className = 'denthub-page-heading';
                wrap.appendChild(headInner);
                subtitle.classList.add('denthub-page-subtitle');
                subtitle.classList.remove('mb-0', 'mb-1', 'mb-2', 'mb-3', 'mb-4');
                wrap.appendChild(subtitle);
                head.appendChild(wrap);
            } else if (headInner === title || (headInner.classList && headInner.classList.contains('denthub-page-title'))) {
                var heading = document.createElement('div');
                heading.className = 'denthub-page-heading';
                heading.appendChild(headInner);
                head.appendChild(heading);
            } else {
                head.appendChild(headInner);
            }

            shell.appendChild(head);
        }

        shell.appendChild(body);
        main.appendChild(shell);
    }

    function run() {
        document.querySelectorAll('main.denthub-main').forEach(enhance);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
