<?php
session_start();
if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true){
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Admin Dashboard — Blissful Weddings (Gold Glass)</title>

  <!-- jsPDF -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

  <style>
    :root{
      --gold: #d4a373;
      --gold-weak: rgba(212,163,115,0.12);
      --bg-dark: #0f0f0f;
      --panel-dark: rgba(20,20,20,0.55);
      --glass-border: rgba(255,255,255,0.06);
      --text: #f3f3f3;
      --muted: #bdbdbd;
    }
    * { box-sizing:border-box; margin:0; padding:0; }
    body{
      font-family:Poppins,system-ui;
      background:linear-gradient(180deg,#080808,#0f0f0f);
      color:var(--text);
      padding:36px;
      overflow:hidden;
      min-height:100vh;
    }
    .app{ height:calc(100vh - 72px); display:flex; flex-direction:column; gap:18px; }
    .topbar{
      display:flex; justify-content:space-between; align-items:center;
      padding:18px 22px; border-radius:14px;
      backdrop-filter:blur(45px) saturate(150%);
      background:rgba(255,255,255,0.03);
      border:1px solid var(--glass-border);
    }
    .brand{ display:flex; gap:14px; align-items:center; }
    .logo{
      width:46px; height:46px; border-radius:10px;
      background:linear-gradient(135deg,rgba(255,255,255,0.06),var(--gold-weak));
      display:flex; align-items:center; justify-content:center;
      font-family:"Playfair Display",serif;
      color:var(--gold); font-weight:700;
    }
    .title{ font-family:"Playfair Display",serif; font-size:1.25rem; color:var(--gold); }
    .controls{ display:flex; gap:10px; align-items:center; }
    .counter{ background:rgba(212,163,115,0.12); padding:8px 12px; border-radius:10px; color:var(--gold); }

    .toggle-track{
      width:62px; height:34px; border-radius:999px;
      background:rgba(255,255,255,0.06);
      display:flex; align-items:center; padding:4px; cursor:pointer;
      border:1px solid rgba(255,255,255,0.03);
    }
    .toggle-knob{
      width:26px; height:26px; border-radius:50%;
      background:white;
      transition:transform .25s;
    }
    .toggle-track.active .toggle-knob{ transform:translateX(28px); }

    .btn{
      padding:10px 14px; border-radius:10px; cursor:pointer;
      background:rgba(255,255,255,0.03); color:var(--text);
      border:1px solid rgba(255,255,255,0.03);
    }

    .content{ display:flex; gap:18px; height:calc(100% - 86px); }
    .panel{
      width:360px; padding:18px; border-radius:14px;
      background:rgba(255,255,255,0.02);
      border:1px solid var(--glass-border);
      backdrop-filter:blur(45px);
      display:flex; flex-direction:column; gap:12px;
    }

    .search input,.select{
      width:100%; padding:12px; border-radius:10px;
      background:rgba(0,0,0,0.35); color:var(--text); border:none;
    }

    .grid-wrap{
      flex:1; overflow:auto; padding:18px; border-radius:14px;
      background:rgba(255,255,255,0.01);
      border:1px solid var(--glass-border);
      backdrop-filter:blur(45px);
    }

    .messages-grid{
      display:grid; gap:14px;
      grid-template-columns:repeat(auto-fill,minmax(300px,1fr));
    }

    .card{
      padding:14px; border-radius:12px;
      background:rgba(255,255,255,0.015);
      border:1px solid rgba(255,255,255,0.03);
      cursor:pointer;
    }

    .delete-btn{
      background:transparent;
      padding:6px 8px; border-radius:8px;
      border:1px solid rgba(255,255,255,0.04);
      color:var(--gold);
    }

    .modal-overlay{
      position:fixed; inset:0;
      background:rgba(0,0,0,0.45);
      backdrop-filter:blur(8px);
      display:flex; align-items:center; justify-content:center;
      z-index:9999;
    }
    .modal{
      width:min(820px,92%);
      padding:20px; border-radius:14px;
      background:rgba(255,255,255,0.04);
      border:1px solid rgba(255,255,255,0.05);
      backdrop-filter:blur(45px);
    }
  </style>
</head>

<body>

<div class="app">
  <div class="topbar">
    <div class="brand">
      <div class="logo">B</div>
      <div>
        <div class="title">Blissful Weddings — Admin</div>
        <div class="small" style="color:var(--muted);">Messages Dashboard</div>
      </div>
    </div>

    <div class="controls">
      <div class="counter" id="counter">Total: 0</div>

      <div class="theme-toggle" title="Toggle theme">
        <div id="themeTrack" class="toggle-track">
          <div class="toggle-knob"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="content">

    <!-- LEFT PANEL -->
    <div class="panel">
      <div class="search">
        <input id="search" placeholder="Search..." />
      </div>

      <select id="sort" class="select">
        <option value="latest">Latest First</option>
        <option value="oldest">Oldest First</option>
      </select>

      <div style="display:flex; gap:8px; margin-top:10px;">
        <button class="btn" onclick="exportCSV()">CSV</button>
        <button class="btn" onclick="exportJSON()">JSON</button>
        <button class="btn" onclick="exportTXT()">TXT</button>
      </div>

      <button class="btn" id="refreshNow" style="margin-top:10px;">⟳ Refresh</button>
    </div>

    <!-- RIGHT GRID -->
    <div class="grid-wrap">
      <div id="messagesGrid" class="messages-grid"></div>
    </div>

  </div>
</div>

<div id="modalRoot" style="display:none;"></div>

<script type="module">
  import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js";
  import {
    getFirestore, collection, getDocs, deleteDoc, doc
  } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-firestore.js";

  const firebaseConfig = {
    apiKey: "AIzaSyCWZSDq_ISBW3QNW9Qjl5n6CvpfIToCsxA",
    authDomain: "blisfull-wedding.firebaseapp.com",
    projectId: "blisfull-wedding",
    storageBucket: "blisfull-wedding.firebasestorage.app",
    messagingSenderId: "644097860130",
    appId: "1:644097860130:web:1bdb0002f7325a11ece07b",
    measurementId: "G-4JJQR6Z6PG"
  };

  const app = initializeApp(firebaseConfig);
  const db = getFirestore(app);

  const messagesGrid = document.getElementById("messagesGrid");
  const counterEl = document.getElementById("counter");
  const searchInput = document.getElementById("search");
  const sortInput = document.getElementById("sort");
  const refreshBtn = document.getElementById("refreshNow");
  const modalRoot = document.getElementById("modalRoot");

  let allMessages = [];

  function getSeconds(msg){
    if(!msg.time) return 0;
    if(msg.time.seconds) return msg.time.seconds;
    if(msg.time._seconds) return msg.time._seconds;
    return 0;
  }

  async function loadMessages(){
    const snap = await getDocs(collection(db,"messages"));
    allMessages = snap.docs.map(d => ({ id:d.id, ...d.data() }));
    renderMessages();
  }

  function renderMessages(){
    let data = [...allMessages];
    const q = searchInput.value.toLowerCase();

    if(q){
      data = data.filter(m =>
        (m.name||"").toLowerCase().includes(q) ||
        (m.email||"").toLowerCase().includes(q) ||
        (m.message||"").toLowerCase().includes(q)
      );
    }

    if(sortInput.value === "latest"){
      data.sort((a,b) => getSeconds(b) - getSeconds(a));
    } else {
      data.sort((a,b) => getSeconds(a) - getSeconds(b));
    }

    counterEl.innerText = "Total: " + data.length;
    messagesGrid.innerHTML = "";

    if(data.length === 0){
      messagesGrid.innerHTML = "<div style='color:var(--muted);'>No messages found.</div>";
      return;
    }

    data.forEach(msg => {
      const div = document.createElement("div");
      div.className = "card";
      div.innerHTML = `
        <h4 style="color:var(--gold); font-family:'Playfair Display',serif;">${msg.name||"No Name"}</h4>
        <div class="meta">${msg.email||"—"} • ${msg.phone||"—"}</div>
        <div class="small" style="color:var(--muted);">${(msg.message||"").slice(0,150)}...</div>
        <button class="delete-btn" data-id="${msg.id}">Delete</button>
      `;

      div.querySelector(".delete-btn").addEventListener("click", async (e)=>{
        e.stopPropagation();
        if(confirm("Delete?")){
          await deleteDoc(doc(db,"messages",msg.id));
          loadMessages();
        }
      });

      messagesGrid.appendChild(div);
    });
  }

  refreshBtn.onclick = loadMessages;
  searchInput.oninput = renderMessages;
  sortInput.onchange = renderMessages;

  // init
  loadMessages();
  setInterval(loadMessages,10000);

  // export funcs
  window.exportCSV = function(){
    let csv = "Name,Email,Phone,Message\n";
    allMessages.forEach(m=>{
      csv += `"${m.name||''}","${m.email||''}","${m.phone||''}","${m.message||''}"\n`;
    });
    download(csv,"messages.csv","text/csv");
  };

  window.exportJSON = function(){
    download(JSON.stringify(allMessages,null,2),"messages.json","application/json");
  };

  window.exportTXT = function(){
    let txt = "";
    allMessages.forEach(m=>{
      txt += `Name: ${m.name}\nEmail: ${m.email}\nPhone: ${m.phone}\nMessage:\n${m.message}\n\n----\n`;
    });
    download(txt,"messages.txt","text/plain");
  };

  function download(data,filename,type){
    const a=document.createElement("a");
    a.href=URL.createObjectURL(new Blob([data],{type}));
    a.download=filename;
    a.click();
  }
</script>

</body>
</html>
