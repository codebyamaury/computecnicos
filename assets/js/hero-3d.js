import * as THREE from 'https://unpkg.com/three@0.160.0/build/three.module.js';

// Configuración de la escena
const scene = new THREE.Scene();
// Niebla roja oscura para profundidad
scene.fog = new THREE.FogExp2(0x000000, 0.002);

const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
const renderer = new THREE.WebGLRenderer({
    alpha: true,
    antialias: true,
    powerPreference: "high-performance"
});

// Obtener dimensiones del contenedor
const container = document.getElementById('hero-canvas-container');
const width = container ? container.clientWidth : window.innerWidth;
const height = container ? container.clientHeight : window.innerHeight;

renderer.setSize(width, height);
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

if (container) {
    container.appendChild(renderer.domElement);
}

// Partículas
const particlesGeometry = new THREE.BufferGeometry();
const particlesCount = 2000;
const posArray = new Float32Array(particlesCount * 3);

for (let i = 0; i < particlesCount * 3; i++) {
    posArray[i] = (Math.random() - 0.5) * 50;
}

particlesGeometry.setAttribute('position', new THREE.BufferAttribute(posArray, 3));

const particlesMaterial = new THREE.PointsMaterial({
    size: 0.05,
    color: 0xff0000,
    transparent: true,
    opacity: 0.8,
    blending: THREE.AdditiveBlending
});

const particlesMesh = new THREE.Points(particlesGeometry, particlesMaterial);
scene.add(particlesMesh);

// Esfera Gigante
// Esfera Gigante
const geometry = new THREE.IcosahedronGeometry(65, 2);
const material = new THREE.MeshBasicMaterial({
    color: 0xff0000,
    wireframe: true,
    transparent: true,
    opacity: 0.15
});
const sphere = new THREE.Mesh(geometry, material);
scene.add(sphere);

const ambientLight = new THREE.AmbientLight(0x404040);
scene.add(ambientLight);

const pointLight = new THREE.PointLight(0xff0000, 2, 100);
pointLight.position.set(10, 10, 10);
scene.add(pointLight);

camera.position.z = 45;

let mouseX = 0;
let mouseY = 0;
let targetX = 0;
let targetY = 0;

document.addEventListener('mousemove', (event) => {
    mouseX = (event.clientX - window.innerWidth / 2);
    mouseY = (event.clientY - window.innerHeight / 2);
});

const clock = new THREE.Clock();

// Variables suavizadas para interpolación (Lerp)
let targetRotateY = 0;
let targetRotateX = 0;
let currentRotateY = 0;
let currentRotateX = 0;

function animate() {
    requestAnimationFrame(animate);
    const elapsedTime = clock.getElapsedTime();

    targetX = mouseX * 0.001;
    targetY = mouseY * 0.001;

    // Calcular rotación objetivo basada en mouse
    const targetSphereY = mouseX * 0.001;
    const targetSphereX = mouseY * 0.001;

    // Interpolación lineal (Lerp) para suavizar el movimiento
    // 0.05 es el factor de "fricción" o suavidad (menor = más suave/lento)
    currentRotateY += (targetSphereY - currentRotateY) * 0.05;
    currentRotateX += (targetSphereX - currentRotateX) * 0.05;

    // Aplicar rotación suavizada
    sphere.rotation.y = (elapsedTime * 0.2) + currentRotateY;
    sphere.rotation.x = (elapsedTime * 0.1) + currentRotateX;

    // También suavizar partículas
    particlesMesh.rotation.y = -currentRotateY * 0.2;
    particlesMesh.rotation.x = -currentRotateX * 0.2;

    // Movimiento de cámara sutil (0.012)
    camera.position.x += (mouseX * 0.012 - camera.position.x) * 0.05;
    camera.position.y += (-mouseY * 0.012 - camera.position.y) * 0.05;
    camera.lookAt(scene.position);

    pointLight.intensity = 2 + Math.sin(elapsedTime * 2) * 1;

    renderer.render(scene, camera);
}

animate();

window.addEventListener('resize', () => {
    if (container) {
        const newWidth = container.clientWidth;
        const newHeight = container.clientHeight;
        camera.aspect = newWidth / newHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(newWidth, newHeight);
    } else {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    }
});
