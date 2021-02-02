import Dashboard from './components/admin/DashboardComponent.vue'
import Profile from './components/admin/ProfileComponent.vue'
// import User from './components/admin/UserComponent.vue'
import Channel from './components/admin/ChannelComponent.vue'

export const routes = [
    {
        path:'/dashboard',
        component:Dashboard
    },
    {
        path:'/profile',
        component:Profile
    },
    // { 
    //     path:'/users',
    //     component:User
    // },
    { 
        path:'/channels',
        component:Channel
    },
 
 
];