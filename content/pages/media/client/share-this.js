!function (e, t) {
    'object' == typeof exports && 'undefined' != typeof module ? module.exports = t() : 'function' == typeof define && define.amd ? define(t) : (e = e || self).ShareThis = t()
}(this, function () {
    'use strict';
    var e;
    function t(t, n) {
        return e || (e = function (e) {
            for (var t = 'atchesSelector', n = 0, r = [
                'matches',
                'm' + t,
                'webkitM' + t,
                'mozM' + t,
                'msM' + t,
                'oM' + t
            ]; n < r.length; n++) {
                var o = r[n];
                if (e[o]) return o
            }
        }(t)),
        t[e](n)
    }
    function n(e, n) {
        for (var r = e; r && (1 !== r.nodeType || !t(r, n)); ) r = r.parentNode;
        return r
    }
    function r(e) {
        return 'function' == typeof e
    }
    function o(e, t, n) {
        var r = n.document,
        o = r.defaultView,
        i = function (e) {
            if (e.isCollapsed) return !0;
            var t = e.anchorNode.compareDocumentPosition(e.focusNode);
            return t ? (4 & t) > 0 : e.anchorOffset < e.focusOffset
        }(o.getSelection()),
        a = function (e, t) {
            var n,
            r = e.getClientRects(),
            o = [
            ].slice.bind(r);
            if (t) {
                for (var i = 1 / 0, a = r.length; a--; ) {
                    var c = r[a];
                    if (c.left > i) break;
                    i = c.left
                }
                n = o(a + 1)
            } else {
                for (var u = - 1 / 0, f = 0; f < r.length; f++) {
                    var s = r[f];
                    if (s.right < u) break;
                    u = s.right
                }
                n = o(0, f)
            }
            return {
                top: Math.min.apply(Math, n.map(function (e) {
                    return e.top
                })),
                bottom: Math.max.apply(Math, n.map(function (e) {
                    return e.bottom
                })),
                left: n[0].left,
                right: n[n.length - 1].right
            }
        }(t, i),
        c = function (e) {
            var t = e.document.body;
            return ('static' === e.getComputedStyle(t).position ? t.parentNode : t).getBoundingClientRect()
        }(o),
        u = e.style;
        i ? u.right = r.documentElement.clientWidth - a.right + c.left + 'px' : u.left = a.left - c.left + 'px',
        u.width = a.right - a.left + 'px',
        u.height = a.bottom - a.top + 'px',
        u.top = a.top - c.top + 'px',
        u.position = 'absolute',
        e.className = n.popoverClass
    }
    var i = 'data-share-via';
    function a(e) {
        return {
            createPopover: function () {
                var t = e.createElement('div');
                return t.addEventListener('click', function (e) {
                    !function (e, t) {
                        var o = n(t.target, '[' + i + ']');
                        if (o) {
                            var a = function (e, t) {
                                for (var n = 0; n < e.length; n++) {
                                    var r = e[n];
                                    if (r.name === t) return r
                                }
                            }(e, o.getAttribute(i));
                            a && r(a.action) && a.action(t, o)
                        }
                    }(this.sharers, e)
                }),
                t
            },
            attachPopover: function (t) {
                e.body.appendChild(t)
            },
            removePopover: function (e) {
                var t = e.parentNode;
                t && t.removeChild(e)
            }
        }
    }
    var c,
    u = function (e, t, n, r) {
        var o = e.shareUrl || e.document.defaultView.location;
        return '<ul>' + t.map(function (e) {
            return '<li data-share-via="' + e.name + '">' + e.render.call(e, n, r, o) + '</li>'
        }).join('') + '</ul>'
    },
    f = [
        'selectionchange',
        'mouseup',
        'touchend',
        'touchcancel'
    ];
    return function (e) {
        var t = (Object.assign || function (e, t) {
            if (t && 'object' == typeof t) for (var n in t) e[n] = t[n];
            return e
        }) ({
            document: document,
            selector: 'body',
            sharers: [
            ],
            popoverClass: 'share-this-popover',
            transformer: function (e) {
                return e.trim().replace(/\s+/g, ' ')
            }
        }, e || {
        }),
        i = !1,
        s = !1,
        l = c,
        p = c,
        d = c,
        v = c;
        return {
            init: function () {
                return !i && (l = t.document, (p = l.defaultView).getSelection ? (f.forEach(h), p.addEventListener('resize', g), v = a(l), i = !0) : (console.warn('share-this: Selection API isn\'t supported'), !1))
            },
            destroy: function () {
                return !(!i || s) && (f.forEach(m), p.removeEventListener('resize', g), C(), l = c, p = c, s = !0)
            },
            reposition: function () {
                return d && o(d, y(), t),
                !!d
            }
        };
        function h(e) {
            l.addEventListener(e, b)
        }
        function m(e) {
            l.removeEventListener(e, b)
        }
        function g() {
            d && o(d, y(), t)
        }
        function b(e) {
            var n = e.type;
            !d != ('selectionchange' === n) && setTimeout(function () {
                if (p) {
                    var e = y();
                    e ? function (e) {
                        var n = !d,
                        i = e.toString(),
                        a = t.transformer(i),
                        f = t.sharers.filter(function (e, t, n) {
                            var o = n.active;
                            return r(o) ? o(e, t) : o === c || o
                        }.bind(null, a, i));
                        f.length ? (n && (d = v.createPopover()), d.sharers = f, d.innerHTML = u(t, f, a, i), o(d, e, t), n && (v.attachPopover(d), r(t.onOpen) && t.onOpen(d, a, i))) : d && C()
                    }(e) : C()
                }
            }, 10)
        }
        function y() {
            var e = p.getSelection(),
            r = e.rangeCount && e.getRangeAt(0);
            if (r) {
                var o = function (e, t) {
                    var r = e.cloneRange();
                    if (e.collapsed || !t) return r;
                    var o = n(e.startContainer, t);
                    return o ? function (e, t) {
                        var n = e.compareDocumentPosition(t);
                        return !n || (16 & n) > 0
                    }(o, e.endContainer) || r.setEnd(o, o.childNodes.length) : (o = n(e.endContainer, t)) ? r.setStart(o, 0) : r.collapse(),
                    r
                }(r, t.selector);
                if (!o.collapsed && o.getClientRects().length) return o
            }
        }
        function C() {
            d && (v.removePopover(d), d = c, r(t.onClose) && t.onClose())
        }
    }
});
//# sourceMappingURL=share-this.js.map