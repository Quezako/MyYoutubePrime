<template>
    <div class="container">
        <div class="row mt-5">
          <div class="col-md-12">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Channels Table</h3>

                <div class="card-tools">
                    <button class="btn btn-success" data-toggle="modal" data-target="#addNew" @click="openModalWindow">Add New <i class="fas fa-channel-plus fa-fw"></i></button>
                </div>
              </div>
             
              <div class="card-body table-responsive p-0">
                <table class="table table-hover">
                  <tbody>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Registered At</th>
                        <th>Modify</th>
                  </tr> 

                  <tr v-for="channel in channels" :key="channel.id">
                    <td>{{ channel.id }}</td>
                    <td>{{ channel.name }}</td>
                    <td>{{ channel.email }}</td>
                    <td>{{ channel.type | strToUpper}}</td>
                    <td>{{ channel.created_at | formatDate}}</td>

                    <td>
                        <a href="#" data-id="channel.id" @click="editModalWindow(channel)">
                            <i class="fa fa-edit blue"></i>
                        </a>
                        |
                        <a href="#" @click="deleteChannel(channel.id)">
                            <i class="fa fa-trash red"></i>
                        </a>

                    </td>
                  </tr>
                </tbody></table>
              </div>
            
              <div class="card-footer">
                 
              </div>
            </div>
           
          </div>
        </div>


            <div class="modal fade" id="addNew" tabindex="-1" role="dialog" aria-labelledby="addNewLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                <div class="modal-header">

                    <h5 v-show="!editMode" class="modal-title" id="addNewLabel">Add New Channel</h5>
                    <h5 v-show="editMode" class="modal-title" id="addNewLabel">Update Channel</h5>

                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                    </button>
                </div>

<form @submit.prevent="editMode ? updateChannel() : createChannel()">
<div class="modal-body">
     <div class="form-group">
        <input v-model="form.name" type="text" name="name"
            placeholder="Name"
            class="form-control" :class="{ 'is-invalid': form.errors.has('name') }">
        <has-error :form="form" field="name"></has-error>
    </div>

     <div class="form-group">
        <input v-model="form.email" type="email" name="email"
            placeholder="Email Address"
            class="form-control" :class="{ 'is-invalid': form.errors.has('email') }">
        <has-error :form="form" field="email"></has-error>
    </div>
    

    <div class="form-group">
        <input v-model="form.password" type="password" name="password" id="password" placeholder="Enter password"
        class="form-control" :class="{ 'is-invalid': form.errors.has('password') }">
        <has-error :form="form" field="password"></has-error>
    </div>

    <div class="form-group">
        <select name="type" v-model="form.type" id="type" class="form-control" :class="{ 'is-invalid': form.errors.has('type') }">
            <option value="">Select Channel Role</option>
            <option value="admin">Admin</option>
            <option value="channel">Standard Channel</option>
            <option value="author">Author</option>
        </select>
        <has-error :form="form" field="type"></has-error>
    </div>

</div>
<div class="modal-footer">
    <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
    <button v-show="editMode" type="submit" class="btn btn-primary">Update</button>
    <button v-show="!editMode" type="submit" class="btn btn-primary">Create</button>
</div>

</form>

                </div>
            </div>
            </div>
    </div>

</template>

<script>
    export default {
        data() {
            return {
                editMode: false,
                channels: {},
                form: new Form({
                    id: '',
                    name : '',
                    email: '',
                    password: '',
                    type: '',

                })
            }
        },
        methods: {
        
        editModalWindow(channel){
           this.form.clear();
           this.editMode = true
           this.form.reset();
           $('#addNew').modal('show');
           this.form.fill(channel)
        },
        updateChannel(){
           this.form.put('api/channel/'+this.form.id)
               .then(()=>{

                   Toast.fire({
                      icon: 'success',
                      title: 'Channel updated successfully'
                    })

                    Fire.$emit('AfterCreatedChannelLoadIt');

                    $('#addNew').modal('hide');
               })
               .catch(()=>{
                  console.log("Error.....")
               })
        },
        openModalWindow(){
           this.editMode = false
           this.form.reset();
           $('#addNew').modal('show');
        },

        loadChannels() {

        axios.get("api/channel").then( data => (this.channels = data.data));

          //pick data from controller and push it into channels object

        },

        createChannel(){

            this.$Progress.start()

            this.form.post('api/channel')
                .then(() => {
                   
                    Fire.$emit('AfterCreatedChannelLoadIt'); //custom events

                        Toast.fire({
                          icon: 'success',
                          title: 'Channel created successfully'
                        })

                        this.$Progress.finish()

                        $('#addNew').modal('hide');

                })
                .catch(() => {
                   console.log("Error......")
                })

     

            //this.loadChannels();
          },
          deleteChannel(id) {
            Swal.fire({
              title: 'Are you sure?',
              text: "You won't be able to revert this!",
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#3085d6',
              cancelButtonColor: '#d33',
              confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                
              if (result.value) {
                //Send Request to server
                this.form.delete('api/channel/'+id)
                    .then((response)=> {
                            Swal.fire(
                              'Deleted!',
                              'Channel deleted successfully',
                              'success'
                            )
                    this.loadChannels();

                    }).catch(() => {
                        Swal.fire({
                          icon: 'error',
                          title: 'Oops...',
                          text: 'Something went wrong!',
                          footer: '<a href>Why do I have this issue?</a>'
                        })
                    })
                }

            })
          }
        },

        created() { 
            this.loadChannels();

            Fire.$on('AfterCreatedChannelLoadIt',()=>{ //custom events fire on
                this.loadChannels();
            });

        }
    }
</script> 