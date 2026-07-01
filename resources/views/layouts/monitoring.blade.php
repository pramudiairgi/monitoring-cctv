<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#000000">
  <title>CCTV Monitoring</title>
  <link rel="preconnect" href="https://livepantau.semarangkota.go.id">
  <link rel="preconnect" href="https://media.pcctabessmg.xyz:5443">
  <link rel="dns-prefetch" href="https://livepantau.semarangkota.go.id">
  <link rel="dns-prefetch" href="https://media.pcctabessmg.xyz:5443">
  @vite(['resources/css/monitoring.css', 'resources/js/monitoring.js'])
</head>
<body>
  <a href="#camera-grid" class="skip-link">Skip to camera grid</a>
  <div id="app">
    @yield('content')
  </div>
</body>
</html>
