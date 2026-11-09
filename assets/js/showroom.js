import * as THREE from 'three';
import { OrbitControls } from 'https://cdn.jsdelivr.net/npm/three@0.169.0/examples/jsm/controls/OrbitControls.js';

(() => {
  const canvas = document.getElementById('showroom-canvas');
  const dataNode = document.getElementById('showroom-product-data');
  if (!canvas || !dataNode) return;

  let products = [];
  try { products = JSON.parse(dataNode.textContent || '[]'); } catch (error) { console.error('K.O.D showroom product data is invalid.', error); }
  if (!products.length) return;

  const $ = id => document.getElementById(id);
  const money = value => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 }).format(Number(value) || 0);
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const fallback = $('viewer-fallback');
  const fallbackImage = $('viewer-fallback-image');
  let selectedIndex = 0;
  let autoRotate = !reducedMotion;
  let renderer, controls, scene, camera, productRoot;
  const clock = new THREE.Clock();

  const materials = {
    ceramic: new THREE.MeshPhysicalMaterial({ color: 0xf3eee5, roughness: 0.2, clearcoat: 0.8, clearcoatRoughness: 0.14 }),
    ceramicShade: new THREE.MeshStandardMaterial({ color: 0xd9cbbb, roughness: 0.25 }),
    coffee: new THREE.MeshPhysicalMaterial({ color: 0x4a2113, roughness: 0.16, clearcoat: 1, clearcoatRoughness: 0.08 }),
    crema: new THREE.MeshStandardMaterial({ color: 0xb97943, roughness: 0.29 }),
    gold: new THREE.MeshStandardMaterial({ color: 0xb88a56, metalness: 0.72, roughness: 0.23 }),
    chocolate: new THREE.MeshPhysicalMaterial({ color: 0x482116, roughness: 0.28, clearcoat: 0.35 }),
    cream: new THREE.MeshPhysicalMaterial({ color: 0xf0e2cf, roughness: 0.34 }),
    pastry: new THREE.MeshPhysicalMaterial({ color: 0xc9823c, roughness: 0.4 }),
    berry: new THREE.MeshPhysicalMaterial({ color: 0x9e3041, roughness: 0.25 }),
  };
  const sharedMaterials = new Set(Object.values(materials));

  function mesh(geometry, material, parent, position = [0, 0, 0], rotation = [0, 0, 0], scale = [1, 1, 1]) {
    const object = new THREE.Mesh(geometry, material);
    object.position.set(...position);
    object.rotation.set(...rotation);
    object.scale.set(...scale);
    parent.add(object);
    return object;
  }

  function makeMug() {
    const group = new THREE.Group();
    const points = [
      new THREE.Vector2(0.48, -0.62), new THREE.Vector2(0.55, -0.56),
      new THREE.Vector2(0.63, 0.38), new THREE.Vector2(0.67, 0.58),
      new THREE.Vector2(0.62, 0.64), new THREE.Vector2(0.53, 0.58),
      new THREE.Vector2(0.46, -0.42), new THREE.Vector2(0.43, -0.55),
    ];
    mesh(new THREE.LatheGeometry(points, 96), materials.ceramic, group, [0, 0.12, 0]);
    const handle = mesh(new THREE.TorusGeometry(0.34, 0.085, 24, 72, Math.PI * 1.72), materials.ceramic, group, [0.64, 0.13, -0.015], [0, 0, -Math.PI * 0.36]);
    handle.scale.set(1, 1.04, 1);
    mesh(new THREE.CylinderGeometry(0.56, 0.56, 0.035, 80), materials.coffee, group, [0, 0.735, 0]);
    mesh(new THREE.TorusGeometry(0.43, 0.036, 12, 72), materials.crema, group, [0, 0.756, 0], [Math.PI / 2, 0, 0]);
    mesh(new THREE.TorusGeometry(0.18, 0.025, 10, 48), materials.cream, group, [0.04, 0.76, 0.03], [Math.PI / 2, 0, 0]);
    mesh(new THREE.CylinderGeometry(0.78, 0.82, 0.09, 80), materials.ceramicShade, group, [0, -0.57, 0]);
    mesh(new THREE.TorusGeometry(0.68, 0.018, 10, 80), materials.gold, group, [0, -0.515, 0], [Math.PI / 2, 0, 0]);
    return group;
  }

  function makeIcedCoffee() {
    const group = new THREE.Group();
    const glass = new THREE.MeshPhysicalMaterial({ color: 0xf7eee4, roughness: 0.08, metalness: 0, transmission: 0.58, transparent: true, opacity: 0.4, thickness: 0.18, clearcoat: 1 });
    const coldCoffee = new THREE.MeshPhysicalMaterial({ color: 0x7a4326, roughness: 0.18, clearcoat: 0.8 });
    const milk = new THREE.MeshStandardMaterial({ color: 0xe5c49d, roughness: 0.25 });
    const profile = [new THREE.Vector2(0.43, -0.72), new THREE.Vector2(0.48, -0.68), new THREE.Vector2(0.59, 0.58), new THREE.Vector2(0.57, 0.68), new THREE.Vector2(0.52, 0.68), new THREE.Vector2(0.48, -0.58), new THREE.Vector2(0.39, -0.65)];
    mesh(new THREE.LatheGeometry(profile, 80), glass, group, [0, 0.05, 0]);
    mesh(new THREE.CylinderGeometry(0.455, 0.405, 1.02, 72), coldCoffee, group, [0, 0.02, 0]);
    mesh(new THREE.CylinderGeometry(0.458, 0.43, 0.27, 72), milk, group, [0, 0.36, 0]);
    mesh(new THREE.TorusGeometry(0.54, 0.025, 10, 72), materials.cream, group, [0, 0.73, 0], [Math.PI / 2, 0, 0]);
    const straw = new THREE.MeshStandardMaterial({ color: 0xe8d6c4, roughness: 0.3 });
    mesh(new THREE.CylinderGeometry(0.035, 0.035, 1.15, 18), straw, group, [0.18, 0.98, 0.06], [0, 0, -0.14]);
    const ice = new THREE.MeshPhysicalMaterial({ color: 0xf4eee5, roughness: 0.08, transmission: 0.4, transparent: true, opacity: 0.72 });
    [[-0.18,0.48,0.08],[0.15,0.06,-0.16],[-0.12,-0.3,-0.08],[0.2,-0.27,0.12]].forEach((p, i) => mesh(new THREE.BoxGeometry(0.22, 0.24, 0.2), ice, group, p, [0.22 * i, 0.3 * i, 0.16 * i]));
    return group;
  }

  function makeTea() {
    const group = new THREE.Group();
    const glass = new THREE.MeshPhysicalMaterial({ color: 0xf5e8d5, roughness: 0.08, transmission: 0.65, transparent: true, opacity: 0.38, thickness: 0.2 });
    const tea = new THREE.MeshPhysicalMaterial({ color: 0xb76d32, roughness: 0.19, clearcoat: 0.6 });
    const profile = [new THREE.Vector2(0.48,-0.55),new THREE.Vector2(0.57,-0.48),new THREE.Vector2(0.65,0.35),new THREE.Vector2(0.6,0.56),new THREE.Vector2(0.53,0.54),new THREE.Vector2(0.49,-0.42)];
    mesh(new THREE.LatheGeometry(profile, 80), glass, group, [0,0.1,0]);
    mesh(new THREE.CylinderGeometry(0.52,0.46,0.74,72), tea, group, [0,0.02,0]);
    mesh(new THREE.TorusGeometry(0.57,0.035,14,72,Math.PI*1.7), glass, group, [0.64,0.2,0], [0,0,-Math.PI*.37]);
    mesh(new THREE.TorusGeometry(0.55,0.02,10,72), materials.gold, group, [0,0.66,0], [Math.PI/2,0,0]);
    return group;
  }

  function makeCake() {
    const group = new THREE.Group();
    const shape = new THREE.Shape(); shape.moveTo(-0.9,-0.66); shape.lineTo(1.0,-0.66); shape.lineTo(0.32,0.72); shape.lineTo(-0.9,-0.66);
    const geometry = new THREE.ExtrudeGeometry(shape, { depth: 0.78, bevelEnabled: true, bevelSegments: 4, steps: 1, bevelSize: 0.055, bevelThickness: 0.065 }); geometry.center();
    mesh(geometry, materials.chocolate, group, [0,0.02,0]);
    mesh(new THREE.BoxGeometry(1.42,0.1,0.58), materials.cream, group, [-0.02,0.51,0.01], [0,-0.2,0]);
    mesh(new THREE.BoxGeometry(1.34,0.08,0.61), new THREE.MeshPhysicalMaterial({color:0x713a28,roughness:.2,clearcoat:.7}), group, [-0.02,0.59,0.01], [0,-0.2,0]);
    for (let i=0;i<3;i++) mesh(new THREE.SphereGeometry(0.12,24,18), materials.berry, group, [-0.38+i*.32,0.72,0.02+(i%2)*.08], [0,0,i*.2]);
    return group;
  }

  function makeDonut() {
    const group = new THREE.Group();
    mesh(new THREE.TorusGeometry(0.72,0.34,48,100), materials.pastry, group, [0,0.12,0], [0.08,0,0]);
    const glaze = new THREE.MeshPhysicalMaterial({ color: 0xe8b9c7, roughness: 0.2, clearcoat: 0.75 });
    mesh(new THREE.TorusGeometry(0.72,0.27,36,100), glaze, group, [0,0.31,0], [0.08,0,0]);
    const sprinkleMaterials = [0x6d8264,0xe5a24e,0x7d596d,0xf4e8d5].map(color => new THREE.MeshStandardMaterial({color,roughness:.32}));
    for (let i=0;i<22;i++) { const a=i*Math.PI*2/22; mesh(new THREE.CapsuleGeometry(0.022,0.105,3,6),sprinkleMaterials[i%4],group,[Math.cos(a)*.79,.57+Math.sin(a*3)*.04,Math.sin(a)*.79],[0,a,0.45]); }
    return group;
  }

  function makeCroissant() {
    const group = new THREE.Group();
    for (let i=0;i<9;i++) {
      const t=i/8; const radius=.17+Math.sin(t*Math.PI)*.34;
      const segment=mesh(new THREE.SphereGeometry(radius,32,24),i%2?materials.pastry:new THREE.MeshPhysicalMaterial({color:0xe0a65d,roughness:.34,clearcoat:.25}),group,[(t-.5)*1.95,Math.sin(t*Math.PI)*.16,Math.sin((t-.5)*Math.PI)*.2],[0,0,(t-.5)*.48],[.86,.72,1.13]);
    }
    return group;
  }

  function makeDessert(product) {
    const label = `${product.name} ${product.slug}`.toLowerCase();
    if (label.includes('donut')) return makeDonut();
    if (label.includes('croissant') || product.category === 'pastry') return makeCroissant();
    if (label.includes('cake') || label.includes('chocolate') || product.category === 'dessert') return makeCake();
    return makeCake();
  }

  function makeProduct(product) {
    if (product.category === 'coffee') return /iced|cold|cloud/.test(`${product.name} ${product.slug}`.toLowerCase()) ? makeIcedCoffee() : makeMug();
    if (product.category === 'tea') return makeTea();
    return makeDessert(product);
  }

  function clearGroup(group) {
    while (group.children.length) {
      const child = group.children[0];
      group.remove(child);
      child.traverse?.(object => {
        if (object.geometry) object.geometry.dispose();
        if (object.material) {
          const list = Array.isArray(object.material) ? object.material : [object.material];
          list.forEach(material => { if (!sharedMaterials.has(material)) material.dispose(); });
        }
      });
    }
  }

  function productMoment(product) {
    const moments = { coffee: 'A SLOW COFFEE BREAK', tea: 'A GENTLE RESET', dessert: 'A LITTLE INDULGENCE', pastry: 'A FRESH MORNING' };
    return moments[product.category] || 'YOUR LITTLE PAUSE';
  }

  function updatePanel(product, index) {
    $('showroom-product-index').textContent = String(index + 1).padStart(2, '0');
    $('showroom-view-label').textContent = `${String(index + 1).padStart(2, '0')} / ${String(products.length).padStart(2, '0')}`;
    $('showroom-product-category').textContent = product.category.toUpperCase();
    $('showroom-product-name').textContent = product.name;
    $('showroom-product-description').textContent = product.description;
    $('showroom-product-moment').textContent = productMoment(product);
    $('showroom-product-price').textContent = money(product.price);
    $('showroom-product-image').src = product.image_url || '';
    $('showroom-product-image').alt = product.name;
    const add = $('showroom-add');
    add.dataset.productId = String(product.id);
    add.dataset.productName = product.name;
    add.dataset.productPrice = String(product.price);
    add.dataset.productImage = product.image_url || '';
    const favorite = $('showroom-favorite');
    if (favorite) {
      favorite.dataset.favoriteId = String(product.id);
      favorite.classList.toggle('is-favorite', Boolean(product.is_favorite));
      favorite.setAttribute('aria-pressed', product.is_favorite ? 'true' : 'false');
      favorite.textContent = product.is_favorite ? '♥ Saved to favourites' : '♡ Save to favourites';
    }
    $('showroom-selection-note').textContent = `SELECT ANOTHER PIECE BELOW TO EXPLORE IT IN 3D.`;
  }

  function selectProduct(index, shouldReset = true) {
    if (!Number.isInteger(index) || !products[index]) return;
    selectedIndex = index;
    const product = products[index];
    document.querySelectorAll('[data-showroom-product]').forEach((button, buttonIndex) => {
      const selected = buttonIndex === index;
      button.classList.toggle('selected', selected);
      button.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
    updatePanel(product, index);
    if (productRoot) {
      clearGroup(productRoot);
      const object = makeProduct(product);
      object.position.y = -0.02;
      productRoot.add(object);
      if (shouldReset && controls) {
        controls.reset();
        controls.autoRotate = autoRotate;
      }
    } else if (fallback) {
      fallbackImage.src = product.image_url || '';
    }
  }

  try {
    scene = new THREE.Scene();
    camera = new THREE.PerspectiveCamera(34, 1, 0.1, 100);
    camera.position.set(4.5, 3.1, 6.1);
    renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true, powerPreference: 'high-performance' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.2;
    scene.add(new THREE.AmbientLight(0xffe8d0, 1.5));
    const key = new THREE.DirectionalLight(0xfff2df, 3.5); key.position.set(4,6,5); scene.add(key);
    const rim = new THREE.PointLight(0xc7834e, 30, 13); rim.position.set(-4,2,-3); scene.add(rim);
    const fill = new THREE.PointLight(0xffffff, 11, 12); fill.position.set(2,-2,5); scene.add(fill);

    const pedestal = new THREE.Group(); scene.add(pedestal);
    mesh(new THREE.CylinderGeometry(1.48,1.56,0.17,96),new THREE.MeshPhysicalMaterial({color:0x5b4030,roughness:.22,metalness:.08,clearcoat:.8}),pedestal,[0,-1.02,0]);
    mesh(new THREE.TorusGeometry(1.36,.022,12,96),new THREE.MeshStandardMaterial({color:0xb98a55,metalness:.75,roughness:.22}),pedestal,[0,-.92,0],[Math.PI/2,0,0]);
    mesh(new THREE.CylinderGeometry(1.08,1.18,.08,96),new THREE.MeshStandardMaterial({color:0x322219,roughness:.35}),pedestal,[0,-.89,0]);
    productRoot = new THREE.Group(); scene.add(productRoot);

    controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true; controls.dampingFactor = .055; controls.enablePan = false;
    controls.minDistance = 3.4; controls.maxDistance = 9; controls.minPolarAngle = .45; controls.maxPolarAngle = 1.9;
    controls.target.set(0,0,0); controls.autoRotate = autoRotate; controls.autoRotateSpeed = .72;

    const resize = () => {
      const width = canvas.clientWidth || 700, height = canvas.clientHeight || 480;
      camera.aspect = width / height; camera.updateProjectionMatrix(); renderer.setSize(width,height,false);
    };
    if ('ResizeObserver' in window) new ResizeObserver(resize).observe(canvas.parentElement);
    window.addEventListener('resize', resize, { passive: true });
    resize();
    const initialIndex = Number(document.querySelector('[data-showroom-initial-index]')?.dataset.showroomInitialIndex || 0);
    selectProduct(Number.isInteger(initialIndex) && initialIndex >= 0 && initialIndex < products.length ? initialIndex : 0, false);
    if (fallback) fallback.hidden = true;

    function animate() {
      requestAnimationFrame(animate);
      const time = clock.getElapsedTime();
      if (productRoot) productRoot.position.y = Math.sin(time * 1.15) * 0.035;
      controls.update(); renderer.render(scene, camera);
    }
    animate();
  } catch (error) {
    console.error('K.O.D 3D showroom could not initialise:', error);
    canvas.hidden = true;
    if (fallback) {
      fallback.hidden = false;
      const initialIndex = Number(document.querySelector('[data-showroom-initial-index]')?.dataset.showroomInitialIndex || 0);
      selectedIndex = Number.isInteger(initialIndex) && products[initialIndex] ? initialIndex : 0;
      fallbackImage.src = products[selectedIndex]?.image_url || '';
    }
  }

  document.addEventListener('kod:favorite-updated', event => {
    const item = products.find(product => Number(product.id) === Number(event.detail?.menuItemId));
    if (item) item.is_favorite = Boolean(event.detail?.isFavorite);
  });

  document.querySelectorAll('[data-showroom-product]').forEach(button => {
    button.addEventListener('click', () => selectProduct(Number(button.dataset.showroomProduct)));
  });

  document.querySelectorAll('[data-showroom-filter]').forEach(button => {
    button.addEventListener('click', () => {
      const category = button.dataset.showroomFilter;
      document.querySelectorAll('[data-showroom-filter]').forEach(filter => {
        const active = filter === button;
        filter.classList.toggle('active', active);
        filter.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
      let firstVisible = -1;
      let visibleCount = 0;
      document.querySelectorAll('[data-showroom-product]').forEach(card => {
        const visible = category === 'all' || card.dataset.category === category;
        card.hidden = !visible;
        if (visible) { visibleCount++; if (firstVisible < 0) firstVisible = Number(card.dataset.showroomProduct); }
      });
      const count = document.querySelector('.showroom-count');
      if (count) count.textContent = `${visibleCount} PIECE${visibleCount === 1 ? '' : 'S'} TO EXPLORE`;
      if (firstVisible >= 0 && document.querySelector(`[data-showroom-product="${selectedIndex}"]`)?.hidden) selectProduct(firstVisible);
    });
  });

  $('showroom-zoom-in')?.addEventListener('click', () => { if (controls) { controls.dollyIn(1.18); controls.update(); } });
  $('showroom-zoom-out')?.addEventListener('click', () => { if (controls) { controls.dollyOut(1.18); controls.update(); } });
  $('showroom-reset')?.addEventListener('click', () => { if (controls) controls.reset(); });
  $('showroom-auto-rotate')?.addEventListener('click', event => {
    autoRotate = !autoRotate;
    if (controls) controls.autoRotate = autoRotate;
    const button = event.currentTarget;
    button.setAttribute('aria-pressed', autoRotate ? 'true' : 'false');
    button.classList.toggle('is-on', autoRotate);
    button.querySelector('span').textContent = autoRotate ? 'ON' : 'OFF';
  });
})();
