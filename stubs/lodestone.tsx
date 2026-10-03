import '../css/lodestone.css';
import { createLodestoneApp } from '@lodestone/react';

createLodestoneApp({
    title: (title) => `${title} · Admin`,
});
