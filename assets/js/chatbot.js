(function () {
  const data = window.PARISH_DATA || {};
  const fab = document.getElementById('chatFab');
  const panel = document.getElementById('chatPanel');
  const closeBtn = document.getElementById('chatClose');
  const body = document.getElementById('chatBody');
  const form = document.getElementById('chatForm');
  const input = document.getElementById('chatInput');
  const suggestedWrap = document.getElementById('chatSuggested');

  const suggestions = [
    'Requirements for baptism?',
    'How much is confirmation?',
    'What are your office hours?',
    'Documents needed for burial?',
    'Is Father available this week?',
    'How do I book an appointment?',
  ];

  function addBubble(text, who) {
    const b = document.createElement('div');
    b.className = 'bubble ' + who;
    b.textContent = text;
    body.appendChild(b);
    body.scrollTop = body.scrollHeight;
    return b;
  }

  function showTyping() {
    const t = document.createElement('div');
    t.className = 'typing';
    t.id = 'typingIndicator';
    t.innerHTML = '<span></span><span></span><span></span>';
    body.appendChild(t);
    body.scrollTop = body.scrollHeight;
  }
  function hideTyping() {
    const t = document.getElementById('typingIndicator');
    if (t) t.remove();
  }

  function findService(text) {
    const map = {
      baptism: ['baptism', 'baptismal', 'binyag'],
      confirmation: ['confirmation', 'kumpil'],
      matrimony: ['wedding', 'matrimony', 'marry', 'marriage', 'kasal'],
      burial: ['burial', 'funeral', 'death', 'libing'],
      intention: ['mass intention', 'intention'],
      anointing: ['anointing', 'sick', 'last rites'],
    };
    for (const key in map) {
      if (map[key].some(w => text.includes(w))) return key;
    }
    return null;
  }

  function reply(raw) {
    const text = raw.toLowerCase();
    const services = data.services || [];
    const reqs = data.requirements || {};

    // fee question
    if (text.includes('fee') || text.includes('how much') || text.includes('cost') || text.includes('price')) {
      const svcKey = findService(text);
      if (svcKey) {
        const svc = services.find(s => s.key === svcKey);
        if (svc) {
          return svc.fee > 0
            ? `${svc.name} has an estimated fee of ₱${svc.fee.toLocaleString()}. This can vary slightly depending on schedule and requirements.`
            : `${svc.name} is offered free of charge, though a love offering is always welcome.`;
        }
      }
      return "Here are our estimated fees — Baptism ₱500, Confirmation ₱400, Matrimony ₱3,500, Burial Mass ₱1,500, Mass Intention ₱150, Anointing of the Sick is free. Scroll down to the Services section for full details.";
    }

    // requirements question
    if (text.includes('requirement') || text.includes('document') || text.includes('need')) {
      const svcKey = findService(text);
      if (svcKey && reqs[svcKey]) {
        return `For ${svcKey === 'intention' ? 'a Mass Intention' : svcKey}, please prepare: ${reqs[svcKey].join(', ')}.`;
      }
      return "Requirements depend on the sacrament — try asking something like 'requirements for baptism' or check the Requirements section below for the full list per service.";
    }

    // office hours
    if (text.includes('office hour') || text.includes('open') || (text.includes('hour') && !text.includes('mass'))) {
      const hours = (data.office_hours || []).map(h => `${h[0]}: ${h[1]}`).join(' · ');
      return `Our parish office hours are — ${hours}.`;
    }

    // mass schedule
    if (text.includes('mass schedule') || text.includes('what time is mass') || text.includes('mass time')) {
      const sched = (data.mass_schedule || []).map(h => `${h[0]} — ${h[1]}`).join(' · ');
      return `Mass schedule: ${sched}.`;
    }

    // priest availability
    if (text.includes('father') || text.includes('priest available') || text.includes('fr.')) {
      const p = data.parish || {};
      return `${p.priest || 'Our parish priest'} is generally available during office hours for consultations. For confessions or urgent sick calls, please contact the office directly at ${p.phone || 'the parish office'}.`;
    }

    // contact info
    if (text.includes('contact') || text.includes('phone') || text.includes('email') || text.includes('address') || text.includes('located')) {
      const p = data.parish || {};
      return `You can reach us at ${p.address}. Phone: ${p.phone} · Email: ${p.email}.`;
    }

    // appointment instructions
    if (text.includes('book') || text.includes('appointment') || text.includes('schedule an')) {
      return "To book: choose a service in the Services section, fill out the request form, upload your documents, pick an available date, and submit your payment (GCash or Cash). You'll get a confirmation once the secretary approves it.";
    }

    return "I can help with sacrament requirements, fees, office hours, Mass schedule, or booking instructions — try one of the suggestions below, or rephrase your question.";
  }

  function respondTo(text) {
    addBubble(text, 'user');
    showTyping();
    const delay = 500 + Math.random() * 500;
    setTimeout(() => {
      hideTyping();
      addBubble(reply(text), 'bot');
    }, delay);
  }

  function renderSuggestions() {
    suggestedWrap.innerHTML = '';
    suggestions.slice(0, 4).forEach(q => {
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'chip';
      chip.textContent = q;
      chip.addEventListener('click', () => respondTo(q));
      suggestedWrap.appendChild(chip);
    });
  }

  let started = false;
  function openChat() {
    panel.classList.add('open');
    fab.setAttribute('aria-expanded', 'true');
    if (!started) {
      started = true;
      const p = data.parish || {};
      addBubble(`Peace be with you! I'm the parish assistant for ${p.name || 'the parish'}. Ask me about sacrament requirements, fees, schedules, or how to book an appointment.`, 'bot');
      renderSuggestions();
    }
    input.focus();
  }
  function closeChat() {
    panel.classList.remove('open');
    fab.setAttribute('aria-expanded', 'false');
  }

  fab.addEventListener('click', () => {
    panel.classList.contains('open') ? closeChat() : openChat();
  });
  closeBtn.addEventListener('click', closeChat);

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const val = input.value.trim();
    if (!val) return;
    input.value = '';
    respondTo(val);
  });
})();