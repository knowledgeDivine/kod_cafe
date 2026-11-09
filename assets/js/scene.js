import * as THREE from 'https://cdn.jsdelivr.net/npm/three@0.169.0/build/three.module.js';
import { OrbitControls } from 'https://cdn.jsdelivr.net/npm/three@0.169.0/examples/jsm/controls/OrbitControls.js';

const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const coffeeCanvas = document.querySelector('#coffee-canvas');
const dessertCanvas = document.querySelector('#dessert-canvas');

function createRenderer(canvas, alpha = true) {
  if (!canvas) return null;
  const renderer = new THREE.WebGLRenderer({ canvas, alpha, antialias: true, powerPreference: 'high-performance' });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.setSize(canvas.clientWidth || 500, canvas.clientHeight || 500, false);
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.15;
  return renderer;
}
function addLights(scene) {
  scene.add(new THREE.AmbientLight(0xffe5c4, 1.25));
  const key = new THREE.DirectionalLight(0xfff0dc, 3.2); key.position.set(4, 6, 5); scene.add(key);
  const rim = new THREE.PointLight(0xc47d43, 26, 13); rim.position.set(-4, 2, -3); scene.add(rim);
  const fill = new THREE.PointLight(0xffffff, 10, 12); fill.position.set(2, -2, 5); scene.add(fill);
}
function makeCoffeeScene(canvas) {
  if (!canvas) return;
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(34, 1, 0.1, 100);
  camera.position.set(5.4, 3.4, 7.4);
  const renderer = createRenderer(canvas, true);
  if (!renderer) return;
  addLights(scene);
  const group = new THREE.Group(); scene.add(group);

  const ceramic = new THREE.MeshPhysicalMaterial({ color: 0xf4eee3, roughness: 0.2, metalness: 0.02, clearcoat: 0.72, clearcoatRoughness: 0.17 });
  const ceramicDark = new THREE.MeshStandardMaterial({ color: 0xd8c9b7, roughness: 0.28 });
  const coffee = new THREE.MeshPhysicalMaterial({ color: 0x4a2414, roughness: 0.16, clearcoat: 1, clearcoatRoughness: 0.08 });
  const gold = new THREE.MeshStandardMaterial({ color: 0xb98a55, metalness: 0.75, roughness: 0.24 });
  const cupPoints = [new THREE.Vector2(0.72, 0), new THREE.Vector2(0.81, 0.08), new THREE.Vector2(0.91, 0.82), new THREE.Vector2(0.94, 1.03), new THREE.Vector2(0.88, 1.08), new THREE.Vector2(0.78, 0.99), new THREE.Vector2(0.69, 0.16), new THREE.Vector2(0.64, 0.08)];
  const cup = new THREE.Mesh(new THREE.LatheGeometry(cupPoints, 96), ceramic); cup.position.y = 0.12; group.add(cup);
  const handle = new THREE.Mesh(new THREE.TorusGeometry(0.43, 0.105, 24, 80, Math.PI * 1.72), ceramic);
  handle.position.set(0.88, 0.72, -0.02); handle.rotation.z = -Math.PI * 0.37; group.add(handle);
  const saucer = new THREE.Mesh(new THREE.CylinderGeometry(1.22, 1.34, 0.11, 96), ceramicDark); saucer.position.y = 0.08; group.add(saucer);
  const saucerLip = new THREE.Mesh(new THREE.TorusGeometry(1.13, 0.025, 12, 96), gold); saucerLip.rotation.x = Math.PI / 2; saucerLip.position.y = 0.145; group.add(saucerLip);
  const liquid = new THREE.Mesh(new THREE.CylinderGeometry(0.815, 0.815, 0.035, 96), coffee); liquid.position.y = 1.13; group.add(liquid);
  const crema = new THREE.Mesh(new THREE.TorusGeometry(0.67, 0.045, 12, 80), new THREE.MeshStandardMaterial({ color: 0xb97943, roughness: 0.3 })); crema.rotation.x = Math.PI / 2; crema.position.y = 1.155; group.add(crema);
  // Small coffee-bean-like garnish
  for (let i = 0; i < 3; i++) {
    const bean = new THREE.Mesh(new THREE.SphereGeometry(0.12, 20, 14), new THREE.MeshStandardMaterial({ color: i % 2 ? 0x63351f : 0x8c4e2d, roughness: 0.4 }));
    bean.scale.set(1.35, 0.48, 0.75); bean.position.set(-1.12 + i * 0.18, 0.2 + i * 0.015, 0.56 + i * 0.1); bean.rotation.z = 0.35 + i * 0.2; group.add(bean);
  }
  // Decorative steam curves
  const steamMaterial = new THREE.MeshBasicMaterial({ color: 0xf5e8d5, transparent: true, opacity: 0.56 });
  const steamLines = [];
  for (let i = 0; i < 3; i++) {
    const points = [];
    for (let j = 0; j <= 24; j++) {
      const t = j / 24;
      points.push(new THREE.Vector3((i - 1) * 0.3 + Math.sin(t * 5 + i) * 0.09, 1.35 + t * 1.45, Math.cos(t * 4 + i) * 0.04));
    }
    const curve = new THREE.CatmullRomCurve3(points);
    const line = new THREE.Mesh(new THREE.TubeGeometry(curve, 44, 0.018, 7, false), steamMaterial.clone());
    line.position.x = (i - 1) * 0.2; group.add(line); steamLines.push(line);
  }
  const controls = new OrbitControls(camera, renderer.domElement);
  controls.enableDamping = true; controls.dampingFactor = 0.045; controls.enablePan = false;
  controls.minDistance = 5; controls.maxDistance = 11; controls.minPolarAngle = 0.55; controls.maxPolarAngle = 1.85;
  controls.target.set(0, 0.8, 0); controls.autoRotate = !prefersReducedMotion; controls.autoRotateSpeed = 0.65;
  const resize = () => { const w = canvas.clientWidth || 500, h = canvas.clientHeight || 500; camera.aspect = w / h; camera.updateProjectionMatrix(); renderer.setSize(w, h, false); };
  new ResizeObserver(resize).observe(canvas); resize();
  const clock = new THREE.Clock();
  function animate() { requestAnimationFrame(animate); const t = clock.getElapsedTime(); steamLines.forEach((line, i) => { line.material.opacity = 0.25 + (Math.sin(t * 1.7 + i) + 1) * 0.14; line.position.y = Math.sin(t * 0.75 + i) * 0.04; }); group.rotation.y += 0.0009; controls.update(); renderer.render(scene, camera); }
  animate();
}

function makeCake() {
  const group = new THREE.Group();
  const chocolate = new THREE.MeshPhysicalMaterial({ color: 0x4b241b, roughness: 0.29, clearcoat: 0.26 });
  const cream = new THREE.MeshStandardMaterial({ color: 0xe7d6be, roughness: 0.5 });
  const berry = new THREE.MeshStandardMaterial({ color: 0x9b2839, roughness: 0.27 });
  const shape = new THREE.Shape();
  shape.moveTo(-1.0, -0.7); shape.lineTo(1.1, -0.7); shape.lineTo(0.45, 0.9); shape.lineTo(-1.0, -0.7);
  const cakeGeometry = new THREE.ExtrudeGeometry(shape, { depth: 0.72, bevelEnabled: true, bevelSegments: 3, steps: 1, bevelSize: 0.06, bevelThickness: 0.07 }); cakeGeometry.center();
  const slice = new THREE.Mesh(cakeGeometry, chocolate); slice.rotation.x = -0.08; group.add(slice);
  const top = new THREE.Mesh(new THREE.BoxGeometry(1.72, 0.07, 0.56), cream); top.position.set(-0.08, 0.42, 0.03); top.rotation.y = -0.2; group.add(top);
  const icing = new THREE.Mesh(new THREE.BoxGeometry(1.62, 0.09, 0.6), new THREE.MeshStandardMaterial({ color: 0x6b3323, roughness: 0.2 })); icing.position.set(-0.08, 0.49, 0.03); icing.rotation.y = -0.2; group.add(icing);
  for (let i = 0; i < 3; i++) { const b = new THREE.Mesh(new THREE.SphereGeometry(0.13, 20, 16), berry); b.position.set(-0.45 + i * 0.32, 0.63 + (i % 2) * 0.03, 0.02); group.add(b); }
  return group;
}
function makeDonut() {
  const group = new THREE.Group();
  const dough = new THREE.MeshPhysicalMaterial({ color: 0xb97843, roughness: 0.38 });
  const glaze = new THREE.MeshPhysicalMaterial({ color: 0xf1d5e0, roughness: 0.22, clearcoat: 0.65 });
  const base = new THREE.Mesh(new THREE.TorusGeometry(0.78, 0.38, 48, 96), dough); base.rotation.x = 0.05; group.add(base);
  const icing = new THREE.Mesh(new THREE.TorusGeometry(0.77, 0.3, 36, 96), glaze); icing.position.y = 0.17; group.add(icing);
  for (let i = 0; i < 18; i++) { const sprinkle = new THREE.Mesh(new THREE.CapsuleGeometry(0.025, 0.13, 3, 6), new THREE.MeshStandardMaterial({ color: [0x6a8d72, 0xe5a24e, 0x7c596b, 0xf3e5c8][i % 4] })); const a = i * Math.PI * 2 / 18; sprinkle.position.set(Math.cos(a) * 0.82, 0.38 + Math.sin(a * 3) * 0.06, Math.sin(a) * 0.82); sprinkle.rotation.z = a; group.add(sprinkle); }
  return group;
}
function makeCroissant() {
  const group = new THREE.Group();
  const pastry = new THREE.MeshStandardMaterial({ color: 0xc78037, roughness: 0.42 });
  const highlight = new THREE.MeshStandardMaterial({ color: 0xe6ad61, roughness: 0.34 });
  for (let i = 0; i < 7; i++) {
    const t = i / 6;
    const radius = 0.22 + Math.sin(t * Math.PI) * 0.36;
    const segment = new THREE.Mesh(new THREE.SphereGeometry(radius, 32, 24), i % 2 ? pastry : highlight);
    segment.scale.set(0.82, 0.72, 1.15);
    segment.position.set((t - 0.5) * 1.8, Math.sin(t * Math.PI) * 0.18, Math.sin((t - 0.5) * Math.PI) * 0.28);
    segment.rotation.z = (t - 0.5) * 0.55;
    group.add(segment);
  }
  return group;
}
function makeDessertScene(canvas) {
  if (!canvas) return;
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(35, 1, 0.1, 100); camera.position.set(4.2, 3.5, 6.8);
  const renderer = createRenderer(canvas, true); if (!renderer) return; addLights(scene);
  const group = new THREE.Group(); scene.add(group);
  const plate = new THREE.Mesh(new THREE.CylinderGeometry(1.55, 1.62, 0.12, 96), new THREE.MeshPhysicalMaterial({ color: 0xe7dfd2, roughness: 0.18, clearcoat: 0.65 })); plate.position.y = -0.55; group.add(plate);
  const plateLine = new THREE.Mesh(new THREE.TorusGeometry(1.43, 0.025, 12, 96), new THREE.MeshStandardMaterial({ color: 0xb68a55, metalness: 0.65, roughness: 0.3 })); plateLine.rotation.x = Math.PI / 2; plateLine.position.y = -0.48; group.add(plateLine);
  let dessert = makeCake(); dessert.position.y = 0.08; group.add(dessert);
  const controls = new OrbitControls(camera, renderer.domElement); controls.enableDamping = true; controls.enablePan = false; controls.minDistance = 4.5; controls.maxDistance = 9; controls.maxPolarAngle = 1.8; controls.target.y = 0.05; controls.autoRotate = !prefersReducedMotion; controls.autoRotateSpeed = 0.6;
  const resize = () => { const w = canvas.clientWidth || 500, h = canvas.clientHeight || 500; camera.aspect = w / h; camera.updateProjectionMatrix(); renderer.setSize(w, h, false); };
  new ResizeObserver(resize).observe(canvas); resize();
  function changeDessert(type) {
    group.remove(dessert); dessert.traverse?.(object => { if (object.geometry) object.geometry.dispose(); });
    dessert = type === 'donut' ? makeDonut() : type === 'croissant' ? makeCroissant() : makeCake();
    dessert.position.y = 0.08; group.add(dessert);
  }
  window.addEventListener('kod:dessert', event => changeDessert(event.detail?.dessert || 'cake'));
  function animate() { requestAnimationFrame(animate); controls.update(); renderer.render(scene, camera); }
  animate();
}

try { makeCoffeeScene(coffeeCanvas); makeDessertScene(dessertCanvas); }
catch (error) { console.error('K.O.D 3D scene could not be initialised:', error); document.querySelectorAll('.three-canvas').forEach(canvas => canvas.classList.add('scene-unavailable')); }
