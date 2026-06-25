// In-browser mock backend. Activated when VITE_USE_MOCK === 'true'.
// Stores data in localStorage so it persists across reloads.

const LS_KEY = 'clinic_mock_db_v1'

const seedDb = () => ({
  users: [
    { id: 1, name: 'Patient One',  email: 'patient@clinic.test', password: 'password', role: 'patient' },
    { id: 2, name: 'Sarah Lee',    email: 'doctor@clinic.test',  password: 'password', role: 'doctor'  },
    { id: 3, name: 'Admin User',   email: 'admin@clinic.test',   password: 'password', role: 'admin'   },
    { id: 4, name: 'John Smith',   email: 'john@clinic.test',    password: 'password', role: 'patient' },
    { id: 5, name: 'Mark Tan',     email: 'mark@clinic.test',    password: 'password', role: 'doctor'  }
  ],
  doctors: [
    { id: 10, user_id: 2, name: 'Sarah Lee', email: 'doctor@clinic.test', specialization: 'Cardiology',  availability: 'Mon–Fri, 9am–5pm' },
    { id: 11, user_id: 5, name: 'Mark Tan',  email: 'mark@clinic.test',   specialization: 'Dermatology', availability: 'Tue–Sat, 10am–6pm' }
  ],
  appointments: [
    { id: 100, patient_id: 1, doctor_id: 10, date: todayPlus(2),  time: '10:00', status: 'pending',   notes: 'Routine check-up' },
    { id: 101, patient_id: 1, doctor_id: 11, date: todayPlus(5),  time: '14:30', status: 'confirmed', notes: 'Skin consult' },
    { id: 102, patient_id: 4, doctor_id: 10, date: todayPlus(1),  time: '09:00', status: 'pending',   notes: '' },
    { id: 103, patient_id: 4, doctor_id: 11, date: todayPlus(-3), time: '11:00', status: 'cancelled', notes: '' }
  ],
  offDays: [
    { id: 200, doctor_id: 10, date: todayPlus(7),  reason: 'Medical conference', status: 'approved' },
    { id: 201, doctor_id: 11, date: todayPlus(10), reason: 'Personal leave',     status: 'pending'  }
  ],
  nextIds: { user: 100, doctor: 100, appointment: 1000, offDay: 2000 }
})

function todayPlus(days) {
  const d = new Date()
  d.setDate(d.getDate() + days)
  return d.toISOString().split('T')[0]
}

function loadDb() {
  try {
    const raw = localStorage.getItem(LS_KEY)
    if (raw) return JSON.parse(raw)
  } catch { /* ignore */ }
  const fresh = seedDb()
  saveDb(fresh)
  return fresh
}

function saveDb(db) { localStorage.setItem(LS_KEY, JSON.stringify(db)) }

// Tiny base64url JWT (NOT secure — just lets the app decode role/id like a real JWT)
function makeToken(user) {
  const enc = (obj) => btoa(JSON.stringify(obj))
    .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
  const header = enc({ alg: 'none', typ: 'JWT' })
  const payload = enc({
    id: user.id, sub: user.id, email: user.email, name: user.name, role: user.role,
    iat: Math.floor(Date.now() / 1000)
  })
  return `${header}.${payload}.mock`
}

const ok = (data, status = 200) => ({
  status, statusText: 'OK', data,
  headers: { 'content-type': 'application/json' }, config: {}
})
const fail = (status, message) => {
  const err = new Error(message)
  err.response = { status, data: { message }, headers: {}, config: {} }
  return err
}

const delay = (ms = 250) => new Promise(r => setTimeout(r, ms))

function enrichOffDay(db, o) {
  const doctorRow = db.doctors.find(d => d.id === o.doctor_id)
  const doctorUser = doctorRow ? db.users.find(u => u.id === doctorRow.user_id) : null
  return {
    ...o,
    doctor: doctorRow ? {
      id: doctorRow.id,
      user_id: doctorRow.user_id,
      name: doctorRow.name || doctorUser?.name,
      email: doctorRow.email || doctorUser?.email,
      specialization: doctorRow.specialization
    } : null
  }
}

function enrichAppointment(db, a) {
  const doctorRow = db.doctors.find(d => d.id === a.doctor_id)
  const doctorUser = doctorRow ? db.users.find(u => u.id === doctorRow.user_id) : null
  const patient = db.users.find(u => u.id === a.patient_id)
  return {
    ...a,
    doctor: doctorRow ? {
      id: doctorRow.id,
      name: doctorRow.name || doctorUser?.name,
      email: doctorRow.email || doctorUser?.email,
      specialization: doctorRow.specialization
    } : null,
    patient: patient ? { id: patient.id, name: patient.name, email: patient.email } : null
  }
}

// The mock router. Returns a Promise that resolves like an axios response.
export async function mockHandle(config) {
  await delay()
  const db = loadDb()
  const url = (config.url || '').replace(/^\/+/, '') // strip leading slash
  const method = (config.method || 'get').toLowerCase()
  let body = config.data
  if (typeof body === 'string') {
    try { body = JSON.parse(body) } catch { body = {} }
  }
  body = body || {}

  // ---------- AUTH ----------
  if (url === 'auth/login' && method === 'post') {
    const u = db.users.find(x => x.email === body.email && x.password === body.password)
    if (!u) throw fail(401, 'Invalid email or password')
    return ok({ token: makeToken(u), user: { id: u.id, name: u.name, email: u.email, role: u.role } })
  }

  if (url === 'auth/register' && method === 'post') {
    if (db.users.some(x => x.email === body.email)) throw fail(422, 'Email already registered')
    const id = ++db.nextIds.user
    const u = { id, name: body.name, email: body.email, password: body.password, role: body.role || 'patient' }
    db.users.push(u)
    saveDb(db)
    return ok({ message: 'Registered', user: { id: u.id, name: u.name, email: u.email, role: u.role } }, 201)
  }

  // ---------- USERS ----------
  if (url === 'users' && method === 'get') {
    return ok(db.users.map(({ password, ...rest }) => rest))
  }

  const userStatusMatch = url.match(/^users\/(\d+)\/status$/)
  if (userStatusMatch && method === 'put') {
    const id = Number(userStatusMatch[1])
    const idx = db.users.findIndex(u => u.id === id)
    if (idx === -1) throw fail(404, 'User not found')
    db.users[idx].is_active = body.is_active !== undefined ? body.is_active : 1
    saveDb(db)
    const { password, ...rest } = db.users[idx]
    return ok(rest)
  }

  const userOne = url.match(/^users\/(\d+)$/)
  if (userOne) {
    const id = Number(userOne[1])
    const idx = db.users.findIndex(u => u.id === id)
    if (idx === -1) throw fail(404, 'User not found')
    if (method === 'put') {
      if (body.name) db.users[idx].name = body.name
      if (body.email) {
        const emailTaken = db.users.some(u => u.email === body.email && u.id !== id)
        if (emailTaken) throw fail(422, 'Email already in use')
        db.users[idx].email = body.email
      }
      saveDb(db)
      const { password, ...rest } = db.users[idx]
      return ok(rest)
    }
    if (method === 'delete') {
      db.users.splice(idx, 1)
      saveDb(db)
      return ok({ message: 'User deleted' })
    }
  }

  // ---------- ME (current user profile) ----------
  // Decode the user id from the bearer token (mock JWTs are base64url JSON)
  const authHeader = (config.headers && (config.headers.Authorization || config.headers.authorization)) || ''
  const tokenStr = authHeader.replace(/^Bearer\s+/i, '')
  let currentUserId = null
  if (tokenStr) {
    try {
      const payload = JSON.parse(atob(tokenStr.split('.')[1].replace(/-/g, '+').replace(/_/g, '/')))
      currentUserId = payload.id || payload.sub
    } catch { /* ignore */ }
  }

  if (url === 'me' && method === 'get') {
    if (!currentUserId) throw fail(401, 'Not authenticated')
    const u = db.users.find(x => x.id === currentUserId)
    if (!u) throw fail(404, 'User not found')
    const { password, ...rest } = u
    let extra = {}
    if (u.role === 'doctor') {
      const doc = db.doctors.find(d => d.user_id === u.id)
      if (doc) extra = { specialization: doc.specialization, availability: doc.availability }
    }
    return ok({ ...rest, ...extra })
  }

  if (url === 'me' && method === 'put') {
    if (!currentUserId) throw fail(401, 'Not authenticated')
    const idx = db.users.findIndex(x => x.id === currentUserId)
    if (idx === -1) throw fail(404, 'User not found')
    const u = db.users[idx]
    if (body.email && body.email !== u.email && db.users.some(x => x.email === body.email)) {
      throw fail(422, 'Email already in use')
    }
    if (body.name) u.name = body.name
    if (body.email) u.email = body.email
    // Sync doctor row if user is a doctor
    if (u.role === 'doctor') {
      const doc = db.doctors.find(d => d.user_id === u.id)
      if (doc) {
        doc.name = u.name
        doc.email = u.email
        if (body.specialization !== undefined) doc.specialization = body.specialization
        if (body.availability !== undefined) doc.availability = body.availability
      }
    }
    saveDb(db)
    const { password, ...rest } = u
    return ok({ ...rest, message: 'Profile updated' })
  }

  if (url === 'me/password' && method === 'put') {
    if (!currentUserId) throw fail(401, 'Not authenticated')
    const u = db.users.find(x => x.id === currentUserId)
    if (!u) throw fail(404, 'User not found')
    if (u.password !== body.current_password) throw fail(422, 'Current password is incorrect')
    if (!body.new_password || body.new_password.length < 6) throw fail(422, 'New password must be at least 6 characters')
    u.password = body.new_password
    saveDb(db)
    return ok({ message: 'Password updated' })
  }

  // ---------- DOCTORS ----------
  if (url === 'doctors' && method === 'get') {
    const list = db.doctors.map(d => {
      const u = db.users.find(x => x.id === d.user_id)
      return { ...d, name: d.name || u?.name, email: d.email || u?.email, user: u ? { id: u.id, name: u.name, email: u.email } : null }
    })
    return ok(list)
  }
  if (url === 'doctors' && method === 'post') {
    const u = db.users.find(x => x.id === Number(body.user_id))
    if (!u) throw fail(422, 'Selected user not found')
    if (u.role !== 'doctor') u.role = 'doctor'
    const id = ++db.nextIds.doctor
    const doc = { id, user_id: u.id, name: u.name, email: u.email, specialization: body.specialization, availability: body.availability || '' }
    db.doctors.push(doc)
    saveDb(db)
    return ok(doc, 201)
  }
  const doctorMatch = url.match(/^doctors\/(\d+)$/)
  if (doctorMatch) {
    const id = Number(doctorMatch[1])
    const idx = db.doctors.findIndex(d => d.id === id)
    if (idx === -1) throw fail(404, 'Doctor not found')
    if (method === 'put') {
      db.doctors[idx] = { ...db.doctors[idx], specialization: body.specialization ?? db.doctors[idx].specialization, availability: body.availability ?? db.doctors[idx].availability }
      saveDb(db)
      return ok(db.doctors[idx])
    }
    if (method === 'delete') {
      db.doctors.splice(idx, 1)
      saveDb(db)
      return ok({ message: 'Deleted' })
    }
  }

  // ---------- APPOINTMENTS ----------
  if (url === 'appointments' && method === 'get') {
    return ok(db.appointments.map(a => enrichAppointment(db, a)))
  }
  if (url === 'appointments' && method === 'post') {
    const id = ++db.nextIds.appointment
    const a = {
      id,
      patient_id: Number(body.patient_id),
      doctor_id: Number(body.doctor_id),
      date: body.date,
      time: body.time,
      notes: body.notes || '',
      status: 'pending'
    }
    db.appointments.push(a)
    saveDb(db)
    return ok(enrichAppointment(db, a), 201)
  }
  const apptByPatient = url.match(/^appointments\/patient\/(\d+)$/)
  if (apptByPatient && method === 'get') {
    const pid = Number(apptByPatient[1])
    return ok(db.appointments.filter(a => a.patient_id === pid).map(a => enrichAppointment(db, a)))
  }
  const apptByDoctor = url.match(/^appointments\/doctor\/(\d+)$/)
  if (apptByDoctor && method === 'get') {
    const userId = Number(apptByDoctor[1])
    // Allow either doctor.id or doctor.user_id (since some flows pass user.id as the doctor route param)
    const docRow = db.doctors.find(d => d.user_id === userId) || db.doctors.find(d => d.id === userId)
    if (!docRow) return ok([])
    return ok(db.appointments.filter(a => a.doctor_id === docRow.id).map(a => enrichAppointment(db, a)))
  }
  const apptOne = url.match(/^appointments\/(\d+)$/)
  if (apptOne) {
    const id = Number(apptOne[1])
    const idx = db.appointments.findIndex(a => a.id === id)
    if (idx === -1) throw fail(404, 'Appointment not found')
    if (method === 'put') {
      db.appointments[idx] = { ...db.appointments[idx], ...body }
      saveDb(db)
      return ok(enrichAppointment(db, db.appointments[idx]))
    }
    if (method === 'delete') {
      db.appointments.splice(idx, 1)
      saveDb(db)
      return ok({ message: 'Deleted' })
    }
  }

  // ---------- OFF DAYS ----------
  if (!db.offDays) db.offDays = []
  if (!db.nextIds.offDay) db.nextIds.offDay = 2000

  if (url === 'off-days' && method === 'get') {
    return ok(db.offDays.map(o => enrichOffDay(db, o)))
  }
  if (url === 'off-days' && method === 'post') {
    const userId = Number(body.user_id) || currentUserId
    let doctorId = Number(body.doctor_id)
    if (!doctorId && userId) {
      const docRow = db.doctors.find(d => d.user_id === userId)
      if (docRow) doctorId = docRow.id
    }
    if (!doctorId) throw fail(422, 'Doctor not found')
    if (!body.date) throw fail(422, 'Date is required')
    const dup = db.offDays.find(o => o.doctor_id === doctorId && o.date === body.date && o.status !== 'rejected')
    if (dup) throw fail(422, 'You already have a request for this date')
    const id = ++db.nextIds.offDay
    const o = { id, doctor_id: doctorId, date: body.date, reason: body.reason || '', status: 'pending' }
    db.offDays.push(o)
    saveDb(db)
    return ok(enrichOffDay(db, o), 201)
  }
  const offByDoctor = url.match(/^off-days\/doctor\/(\d+)$/)
  if (offByDoctor && method === 'get') {
    const id = Number(offByDoctor[1])
    const docRow = db.doctors.find(d => d.user_id === id) || db.doctors.find(d => d.id === id)
    if (!docRow) return ok([])
    return ok(db.offDays.filter(o => o.doctor_id === docRow.id).map(o => enrichOffDay(db, o)))
  }
  const offOne = url.match(/^off-days\/(\d+)$/)
  if (offOne) {
    const id = Number(offOne[1])
    const idx = db.offDays.findIndex(o => o.id === id)
    if (idx === -1) throw fail(404, 'Off day not found')
    if (method === 'put') {
      db.offDays[idx] = { ...db.offDays[idx], ...body }
      saveDb(db)
      return ok(enrichOffDay(db, db.offDays[idx]))
    }
    if (method === 'delete') {
      db.offDays.splice(idx, 1)
      saveDb(db)
      return ok({ message: 'Deleted' })
    }
  }

  const apptComplete = url.match(/^appointments\/(\d+)\/complete$/)
  if (apptComplete && method === 'put') {
    const id = Number(apptComplete[1])
    const idx = db.appointments.findIndex(a => a.id === id)
    if (idx === -1) throw fail(404, 'Appointment not found')
    db.appointments[idx].status = 'completed'
    saveDb(db)
    return ok(enrichAppointment(db, db.appointments[idx]))
  }

  const apptComment = url.match(/^appointments\/(\d+)\/comment$/)
  if (apptComment && method === 'put') {
    const id = Number(apptComment[1])
    const idx = db.appointments.findIndex(a => a.id === id)
    if (idx === -1) throw fail(404, 'Appointment not found')
    db.appointments[idx].doctor_comment = body.doctor_comment || ''
    saveDb(db)
    return ok(enrichAppointment(db, db.appointments[idx]))
  }

  throw fail(404, `Mock route not found: ${method.toUpperCase()} /${url}`)
}

export function resetMockDb() {
  localStorage.removeItem(LS_KEY)
}
