(function(){
    function initOne(el){
        if (!window.$3Dmol || el.getAttribute('data-wcpdbv-init')) return;
        el.setAttribute('data-wcpdbv-init','1');
        try {
            var url = el.getAttribute('data-url');
            var typ = el.getAttribute('data-type') || 'pdb';
            var ui  = el.getAttribute('data-ui') === 'true';
            var nm  = el.getAttribute('data-nomouse') === 'true';
            var bga = parseFloat(el.getAttribute('data-bgalpha')||'0');
            var sty = el.getAttribute('data-style');
            var spin= el.getAttribute('data-spin');
            var style = {};
            try { style = JSON.parse(sty||'{}'); } catch(e){}
            var opts = { backgroundAlpha:bga, disableMouse: nm };
            var viewer = $3Dmol.createViewer(el, opts);
            fetch(url).then(r=>r.text()).then(function(data){
                viewer.addModel(data, typ);
                viewer.setStyle({}, style||{stick:{}});
                viewer.zoomTo(); viewer.render();
                if (spin) {
                    var axis = 'y', speed = 1;
                    if (spin !== 'true') {
                        var parts = spin.split(':');
                        axis = (parts[0] || 'y').toLowerCase();
                        speed = parseFloat(parts[1] || '1');
                        if (isNaN(speed)) speed = 1;
                    }
                    if (viewer.spin) { try { viewer.spin(axis, speed); } catch(e){} }
                }
            });
            if (ui && window.$3Dmol.UI){ new $3Dmol.UI(viewer); }
            el._wcpdbv = viewer;
        } catch(e){}
    }

    function initAll(){
        var els = document.querySelectorAll('.wcpdbv-viewer');
        for (var i=0;i<els.length;i++){ initOne(els[i]); }
    }

    // Lazy init when visible
    if ('IntersectionObserver' in window){
        var io = new IntersectionObserver(function(entries){
            entries.forEach(function(en){ if (en.isIntersecting) { initOne(en.target); io.unobserve(en.target); } });
        }, {root:null, rootMargin:'200px', threshold:0.01});
        document.addEventListener('DOMContentLoaded', function(){
            document.querySelectorAll('.wcpdbv-viewer').forEach(function(el){ io.observe(el); });
        });
    } else {
        document.addEventListener('DOMContentLoaded', initAll);
    }

    // Variation switching
    (function($){
        if (!window.jQuery) return;
        $(document).on('found_variation', 'form.variations_form', function(e, variation){
            if (!variation || !variation.variation_id) return;
            var map = (window.WCPDBV_VAR && WCPDBV_VAR.variationMap) || {};
            var newUrl = map[String(variation.variation_id)];
            if (!newUrl) return;
            var el = document.querySelector('.wcpdbv-viewer');
            if (!el || !el._wcpdbv) return;
            el.setAttribute('data-url', newUrl);
            fetch(newUrl).then(r=>r.text()).then(function(data){
                var viewer = el._wcpdbv;
                viewer.removeAllModels();
                var typ = el.getAttribute('data-type') || 'pdb';
                viewer.addModel(data, typ);
                try { var style = JSON.parse(el.getAttribute('data-style')||'{}'); } catch(e){ style = {stick:{}}; }
                viewer.setStyle({}, style||{stick:{}});
                viewer.zoomTo(); viewer.render();
                var spin= el.getAttribute('data-spin');
                if (spin) {
                    var axis = 'y', speed = 1;
                    if (spin !== 'true') {
                        var parts = spin.split(':');
                        axis = (parts[0] || 'y').toLowerCase();
                        speed = parseFloat(parts[1] || '1');
                        if (isNaN(speed)) speed = 1;
                    }
                    if (viewer.spin) { try { viewer.spin(axis, speed); } catch(e){} }
                }
            });
        });
    })(window.jQuery || {});
})();
